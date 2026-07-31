<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Property;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_report_own_accepted_booking(): void
    {
        $guest = User::factory()->create();
        $property = $this->propertyFor(User::factory()->create());
        $booking = $this->bookingFor($guest, $property, ['status' => Booking::STATUS_ACCEPTED]);

        $this
            ->actingAs($guest, 'sanctum')
            ->postJson('/api/reports', [
                'property_id' => $property->id,
                'booking_id' => $booking->id,
                'category' => Report::CATEGORY_HOST_ISSUE,
                'description' => 'The host was not reachable.',
            ])
            ->assertCreated()
            ->assertJsonPath('message', 'Report submitted successfully.')
            ->assertJsonPath('report.property_id', $property->id)
            ->assertJsonPath('report.booking_id', $booking->id)
            ->assertJsonPath('report.category', Report::CATEGORY_HOST_ISSUE)
            ->assertJsonPath('report.status', Report::STATUS_PENDING)
            ->assertJsonPath('report.severity', Report::SEVERITY_NORMAL);

        $this->assertDatabaseHas('reports', [
            'reporter_user_id' => $guest->id,
            'reported_user_id' => $property->user_id,
            'property_id' => $property->id,
            'booking_id' => $booking->id,
            'status' => Report::STATUS_PENDING,
            'severity' => Report::SEVERITY_NORMAL,
        ]);
    }

    public function test_guest_can_report_own_completed_booking(): void
    {
        $guest = User::factory()->create();
        $property = $this->propertyFor(User::factory()->create());
        $booking = $this->bookingFor($guest, $property, ['status' => Booking::STATUS_COMPLETED]);

        $this
            ->actingAs($guest, 'sanctum')
            ->postJson('/api/reports', $this->reportPayload($property, $booking))
            ->assertCreated();
    }

    public function test_guest_can_report_own_cancelled_booking(): void
    {
        $guest = User::factory()->create();
        $property = $this->propertyFor(User::factory()->create());
        $booking = $this->bookingFor($guest, $property, ['status' => Booking::STATUS_CANCELLED]);

        $this
            ->actingAs($guest, 'sanctum')
            ->postJson('/api/reports', $this->reportPayload($property, $booking))
            ->assertCreated();
    }

    public function test_guest_cannot_report_another_users_booking(): void
    {
        $guest = User::factory()->create();
        $property = $this->propertyFor(User::factory()->create());
        $booking = $this->bookingFor(User::factory()->create(), $property, ['status' => Booking::STATUS_ACCEPTED]);

        $this
            ->actingAs($guest, 'sanctum')
            ->postJson('/api/reports', $this->reportPayload($property, $booking))
            ->assertForbidden()
            ->assertJsonPath('message', 'This booking cannot be reported.');

        $this->assertSame(0, Report::count());
    }

    public function test_owner_cannot_report_own_property(): void
    {
        $owner = User::factory()->create();
        $property = $this->propertyFor($owner);
        $booking = $this->bookingFor($owner, $property, ['status' => Booking::STATUS_ACCEPTED]);

        $this
            ->actingAs($owner, 'sanctum')
            ->postJson('/api/reports', $this->reportPayload($property, $booking))
            ->assertForbidden()
            ->assertJsonPath('message', 'This booking cannot be reported.');
    }

    public function test_user_cannot_report_booking_property_mismatch(): void
    {
        $guest = User::factory()->create();
        $owner = User::factory()->create();
        $property = $this->propertyFor($owner);
        $otherProperty = $this->propertyFor($owner, ['title' => 'Other Suite']);
        $booking = $this->bookingFor($guest, $otherProperty, ['status' => Booking::STATUS_ACCEPTED]);

        $this
            ->actingAs($guest, 'sanctum')
            ->postJson('/api/reports', $this->reportPayload($property, $booking))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'This booking cannot be reported.');
    }

    public function test_user_cannot_report_pending_or_rejected_booking(): void
    {
        $guest = User::factory()->create();
        $property = $this->propertyFor(User::factory()->create());

        foreach ([Booking::STATUS_PENDING, Booking::STATUS_REJECTED] as $status) {
            $booking = $this->bookingFor($guest, $property, ['status' => $status]);

            $this
                ->actingAs($guest, 'sanctum')
                ->postJson('/api/reports', $this->reportPayload($property, $booking))
                ->assertUnprocessable()
                ->assertJsonPath('message', 'This booking cannot be reported.');
        }

        $this->assertSame(0, Report::count());
    }

    public function test_duplicate_report_for_booking_returns_conflict(): void
    {
        $guest = User::factory()->create();
        $property = $this->propertyFor(User::factory()->create());
        $booking = $this->bookingFor($guest, $property, ['status' => Booking::STATUS_ACCEPTED]);

        Report::create([
            'reporter_user_id' => $guest->id,
            'property_id' => $property->id,
            'booking_id' => $booking->id,
            'reported_user_id' => $property->user_id,
            'category' => Report::CATEGORY_HOST_ISSUE,
            'status' => Report::STATUS_REVIEWED,
            'severity' => Report::SEVERITY_NORMAL,
        ]);

        $this
            ->actingAs($guest, 'sanctum')
            ->postJson('/api/reports', $this->reportPayload($property, $booking))
            ->assertConflict()
            ->assertJsonPath('message', 'A report for this booking is already under review.');

        $this->assertSame(1, Report::count());
    }

    public function test_report_severity_is_high_for_unsafe_or_fraud_categories(): void
    {
        $guest = User::factory()->create();
        $property = $this->propertyFor(User::factory()->create());
        $unsafeBooking = $this->bookingFor($guest, $property, ['status' => Booking::STATUS_ACCEPTED]);
        $fraudBooking = $this->bookingFor($guest, $property, ['status' => Booking::STATUS_ACCEPTED]);

        $this
            ->actingAs($guest, 'sanctum')
            ->postJson('/api/reports', $this->reportPayload($property, $unsafeBooking, Report::CATEGORY_UNSAFE_PROPERTY))
            ->assertCreated()
            ->assertJsonPath('report.severity', Report::SEVERITY_HIGH);

        $this
            ->actingAs($guest, 'sanctum')
            ->postJson('/api/reports', $this->reportPayload($property, $fraudBooking, Report::CATEGORY_SCAM_OR_FRAUD))
            ->assertCreated()
            ->assertJsonPath('report.severity', Report::SEVERITY_HIGH);
    }

    public function test_report_response_exposes_only_safe_fields(): void
    {
        $guest = User::factory()->create();
        $property = $this->propertyFor(User::factory()->create());
        $booking = $this->bookingFor($guest, $property, ['status' => Booking::STATUS_ACCEPTED]);

        $response = $this
            ->actingAs($guest, 'sanctum')
            ->postJson('/api/reports', $this->reportPayload($property, $booking))
            ->assertCreated();

        $report = $response->json('report');

        $this->assertArrayNotHasKey('admin_notes', $report);
        $this->assertArrayNotHasKey('reviewed_by_user_id', $report);
        $this->assertArrayNotHasKey('reported_user_id', $report);
        $this->assertArrayNotHasKey('reporter_user_id', $report);
        $this->assertArrayNotHasKey('reporter', $report);
        $this->assertArrayNotHasKey('reported_user', $report);
    }

    public function test_unauthenticated_user_cannot_report(): void
    {
        $this
            ->postJson('/api/reports', [
                'property_id' => 1,
                'booking_id' => 1,
                'category' => Report::CATEGORY_HOST_ISSUE,
            ])
            ->assertUnauthorized();
    }

    private function reportPayload(Property $property, Booking $booking, string $category = Report::CATEGORY_HOST_ISSUE): array
    {
        return [
            'property_id' => $property->id,
            'booking_id' => $booking->id,
            'category' => $category,
            'description' => 'A concise issue description.',
        ];
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
