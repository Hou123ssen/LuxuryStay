<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Favorite;
use App\Models\Property;
use App\Models\Report;
use App\Models\Review;
use App\Models\User;
use App\Support\PropertyReportSignals;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropertyReportSignalsTest extends TestCase
{
    use RefreshDatabase;

    public function test_property_with_no_reports_returns_clear_state(): void
    {
        $property = $this->propertyFor(User::factory()->create());

        $this->assertSame([
            'unresolved_reports_count' => 0,
            'unresolved_high_reports_count' => 0,
            'unresolved_critical_reports_count' => 0,
            'unresolved_safety_reports_count' => 0,
            'unresolved_fraud_reports_count' => 0,
            'report_signal_state' => PropertyReportSignals::STATE_CLEAR,
            'report_signal_label' => 'No unresolved report signals',
        ], PropertyReportSignals::forProperty($property));
    }

    public function test_pending_normal_report_returns_attention(): void
    {
        $property = $this->propertyFor(User::factory()->create());
        $this->reportFor($property, [
            'status' => Report::STATUS_PENDING,
            'severity' => Report::SEVERITY_NORMAL,
            'category' => Report::CATEGORY_HOST_ISSUE,
        ]);

        $signals = PropertyReportSignals::forProperty($property);

        $this->assertSame(1, $signals['unresolved_reports_count']);
        $this->assertSame(PropertyReportSignals::STATE_ATTENTION, $signals['report_signal_state']);
        $this->assertSame('Unresolved reports under review', $signals['report_signal_label']);
    }

    public function test_reviewed_normal_report_returns_attention(): void
    {
        $property = $this->propertyFor(User::factory()->create());
        $this->reportFor($property, [
            'status' => Report::STATUS_REVIEWED,
            'severity' => Report::SEVERITY_NORMAL,
            'category' => Report::CATEGORY_HOST_ISSUE,
        ]);

        $this->assertSame(
            PropertyReportSignals::STATE_ATTENTION,
            PropertyReportSignals::forProperty($property)['report_signal_state']
        );
    }

    public function test_resolved_and_rejected_reports_do_not_count(): void
    {
        $property = $this->propertyFor(User::factory()->create());
        $this->reportFor($property, ['status' => Report::STATUS_RESOLVED]);
        $this->reportFor($property, ['status' => Report::STATUS_REJECTED]);

        $signals = PropertyReportSignals::forProperty($property);

        $this->assertSame(0, $signals['unresolved_reports_count']);
        $this->assertSame(PropertyReportSignals::STATE_CLEAR, $signals['report_signal_state']);
    }

    public function test_pending_high_or_critical_severity_returns_serious_attention(): void
    {
        $highProperty = $this->propertyFor(User::factory()->create());
        $criticalProperty = $this->propertyFor(User::factory()->create());
        $this->reportFor($highProperty, ['severity' => Report::SEVERITY_HIGH]);
        $this->reportFor($criticalProperty, ['severity' => Report::SEVERITY_CRITICAL]);

        $this->assertSame(
            PropertyReportSignals::STATE_SERIOUS_ATTENTION,
            PropertyReportSignals::forProperty($highProperty)['report_signal_state']
        );
        $this->assertSame(
            PropertyReportSignals::STATE_SERIOUS_ATTENTION,
            PropertyReportSignals::forProperty($criticalProperty)['report_signal_state']
        );
    }

    public function test_unsafe_or_fraud_category_returns_serious_attention(): void
    {
        $unsafeProperty = $this->propertyFor(User::factory()->create());
        $fraudProperty = $this->propertyFor(User::factory()->create());
        $this->reportFor($unsafeProperty, [
            'severity' => Report::SEVERITY_NORMAL,
            'category' => Report::CATEGORY_UNSAFE_PROPERTY,
        ]);
        $this->reportFor($fraudProperty, [
            'severity' => Report::SEVERITY_NORMAL,
            'category' => Report::CATEGORY_SCAM_OR_FRAUD,
        ]);

        $this->assertSame(
            PropertyReportSignals::STATE_SERIOUS_ATTENTION,
            PropertyReportSignals::forProperty($unsafeProperty)['report_signal_state']
        );
        $this->assertSame(
            PropertyReportSignals::STATE_SERIOUS_ATTENTION,
            PropertyReportSignals::forProperty($fraudProperty)['report_signal_state']
        );
    }

    public function test_counts_are_calculated_correctly(): void
    {
        $property = $this->propertyFor(User::factory()->create());
        $this->reportFor($property, ['severity' => Report::SEVERITY_HIGH]);
        $this->reportFor($property, ['severity' => Report::SEVERITY_CRITICAL]);
        $this->reportFor($property, [
            'severity' => Report::SEVERITY_NORMAL,
            'category' => Report::CATEGORY_UNSAFE_PROPERTY,
        ]);
        $this->reportFor($property, [
            'severity' => Report::SEVERITY_NORMAL,
            'category' => Report::CATEGORY_SCAM_OR_FRAUD,
        ]);
        $this->reportFor($property, ['status' => Report::STATUS_RESOLVED, 'severity' => Report::SEVERITY_HIGH]);

        $signals = PropertyReportSignals::forProperty($property);

        $this->assertSame(4, $signals['unresolved_reports_count']);
        $this->assertSame(1, $signals['unresolved_high_reports_count']);
        $this->assertSame(1, $signals['unresolved_critical_reports_count']);
        $this->assertSame(1, $signals['unresolved_safety_reports_count']);
        $this->assertSame(1, $signals['unresolved_fraud_reports_count']);
        $this->assertSame(PropertyReportSignals::STATE_SERIOUS_ATTENTION, $signals['report_signal_state']);
        $this->assertSame('Serious unresolved reports under review', $signals['report_signal_label']);
    }

    public function test_public_property_payloads_do_not_expose_report_signals(): void
    {
        $viewer = User::factory()->create();
        $property = $this->propertyFor(User::factory()->create());
        Favorite::create(['user_id' => $viewer->id, 'property_id' => $property->id]);
        $this->reportFor($property, [
            'severity' => Report::SEVERITY_HIGH,
            'category' => Report::CATEGORY_UNSAFE_PROPERTY,
            'description' => 'Internal report description.',
        ]);

        $index = $this
            ->actingAs($viewer, 'sanctum')
            ->getJson('/api/properties')
            ->assertOk();

        $detail = $this
            ->actingAs($viewer, 'sanctum')
            ->getJson('/api/properties/'.$property->id)
            ->assertOk();

        $favorites = $this
            ->actingAs($viewer, 'sanctum')
            ->getJson('/api/favorites')
            ->assertOk();

        foreach ([$index->json('data.0'), $detail->json(), $favorites->json('data.0.property')] as $payload) {
            $this->assertReportSignalsArePrivate($payload);
        }
    }

    public function test_current_trust_badge_output_remains_unchanged(): void
    {
        $viewer = User::factory()->create();
        $property = $this->propertyFor(User::factory()->create());
        $this->addPublishedReviews($property, 10, 5);
        $this->reportFor($property, [
            'severity' => Report::SEVERITY_HIGH,
            'category' => Report::CATEGORY_UNSAFE_PROPERTY,
        ]);

        $this
            ->actingAs($viewer, 'sanctum')
            ->getJson('/api/properties/'.$property->id)
            ->assertOk()
            ->assertJsonPath('trust_badge', 'trusted')
            ->assertJsonPath('trust_label', 'Trusted')
            ->assertJsonMissingPath('report_signal_state')
            ->assertJsonMissingPath('unresolved_reports_count');
    }

    private function assertReportSignalsArePrivate(array $payload): void
    {
        $this->assertArrayNotHasKey('unresolved_reports_count', $payload);
        $this->assertArrayNotHasKey('unresolved_high_reports_count', $payload);
        $this->assertArrayNotHasKey('unresolved_critical_reports_count', $payload);
        $this->assertArrayNotHasKey('unresolved_safety_reports_count', $payload);
        $this->assertArrayNotHasKey('unresolved_fraud_reports_count', $payload);
        $this->assertArrayNotHasKey('report_signal_state', $payload);
        $this->assertArrayNotHasKey('report_signal_label', $payload);
        $this->assertArrayNotHasKey('admin_notes', $payload);
        $this->assertArrayNotHasKey('reports', $payload);
    }

    private function reportFor(Property $property, array $attributes = []): Report
    {
        $guest = User::factory()->create();
        $booking = $this->bookingFor($guest, $property);

        return Report::create(array_merge([
            'reporter_user_id' => $guest->id,
            'property_id' => $property->id,
            'booking_id' => $booking->id,
            'reported_user_id' => $property->user_id,
            'category' => Report::CATEGORY_HOST_ISSUE,
            'description' => 'A report description.',
            'status' => Report::STATUS_PENDING,
            'severity' => Report::SEVERITY_NORMAL,
        ], $attributes));
    }

    private function addPublishedReviews(Property $property, int $count, int $rating): void
    {
        for ($i = 0; $i < $count; $i++) {
            $guest = User::factory()->create();
            $booking = $this->bookingFor($guest, $property, [
                'start_date' => now()->subDays(10 + $i)->toDateString(),
                'end_date' => now()->subDays(8 + $i)->toDateString(),
            ]);

            Review::create([
                'user_id' => $guest->id,
                'property_id' => $property->id,
                'booking_id' => $booking->id,
                'rating' => $rating,
                'comment' => 'A verified published review.',
            ]);
        }
    }

    private function propertyFor(User $user, array $attributes = []): Property
    {
        return Property::create(array_merge([
            'user_id' => $user->id,
            'title' => 'Owner Suite',
            'description' => 'A calm test property.',
            'type' => 'apartment',
            'price_per_night' => 250,
            'city' => 'Marrakech',
            'address' => 'Medina',
        ], $attributes));
    }

    private function bookingFor(User $user, Property $property, array $attributes = []): Booking
    {
        return Booking::create(array_merge([
            'user_id' => $user->id,
            'property_id' => $property->id,
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-03',
            'total_price' => 500,
            'status' => Booking::STATUS_ACCEPTED,
        ], $attributes));
    }
}
