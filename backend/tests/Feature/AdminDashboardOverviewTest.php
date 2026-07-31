<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Property;
use App\Models\Report;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_overview(): void
    {
        $this->getJson('/api/admin/dashboard/overview')->assertUnauthorized();
    }

    public function test_normal_user_and_property_owner_cannot_access_overview(): void
    {
        $normalUser = User::factory()->create();
        $propertyOwner = User::factory()->create(['role' => 'owner']);
        $this->propertyFor($propertyOwner);

        $this
            ->actingAs($normalUser, 'sanctum')
            ->getJson('/api/admin/dashboard/overview')
            ->assertForbidden()
            ->assertJsonPath('message', 'This action is unauthorized.');

        $this
            ->actingAs($propertyOwner, 'sanctum')
            ->getJson('/api/admin/dashboard/overview')
            ->assertForbidden()
            ->assertJsonPath('message', 'This action is unauthorized.');
    }

    public function test_admin_can_access_overview_with_all_main_sections(): void
    {
        $admin = $this->admin();
        $owner = User::factory()->create(['role' => 'owner']);
        $guest = User::factory()->create(['role' => 'guest']);
        $property = $this->propertyFor($owner);
        $booking = $this->bookingFor($guest, $property, ['status' => Booking::STATUS_PENDING]);
        $this->reportFor($guest, $property, $booking, ['status' => Report::STATUS_PENDING]);
        $this->reviewFor($guest, $property, $booking, ['status' => Review::STATUS_PUBLISHED]);

        $this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/dashboard/overview')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'totals',
                    'bookings',
                    'moderation',
                    'trust_and_safety',
                    'recent_activity' => ['bookings', 'reports', 'reviews'],
                    'alerts',
                ],
            ]);
    }

    public function test_totals_count_platform_records_correctly(): void
    {
        $admin = $this->admin();
        $owner = User::factory()->create(['role' => 'owner']);
        $guest = User::factory()->create(['role' => 'guest']);
        $property = $this->propertyFor($owner);
        $booking = $this->bookingFor($guest, $property);
        $this->reportFor($guest, $property, $booking);
        $this->reviewFor($guest, $property, $booking);

        $this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/dashboard/overview')
            ->assertOk()
            ->assertJsonPath('data.totals.users_count', 3)
            ->assertJsonPath('data.totals.guests_count', 1)
            ->assertJsonPath('data.totals.owners_count', 1)
            ->assertJsonPath('data.totals.admins_count', 1)
            ->assertJsonPath('data.totals.properties_count', 1)
            ->assertJsonPath('data.totals.bookings_count', 1)
            ->assertJsonPath('data.totals.reviews_count', 1)
            ->assertJsonPath('data.totals.reports_count', 1);
    }

    public function test_booking_counts_are_grouped_by_status_and_cancellation_actor(): void
    {
        $admin = $this->admin();
        $owner = User::factory()->create(['role' => 'owner']);
        $guest = User::factory()->create(['role' => 'guest']);
        $property = $this->propertyFor($owner);

        $this->bookingFor($guest, $property, ['status' => Booking::STATUS_PENDING]);
        $this->bookingFor($guest, $property, ['status' => Booking::STATUS_ACCEPTED]);
        $this->bookingFor($guest, $property, ['status' => Booking::STATUS_COMPLETED]);
        $this->bookingFor($guest, $property, ['status' => Booking::STATUS_REJECTED]);
        $this->bookingFor($guest, $property, [
            'status' => Booking::STATUS_CANCELLED,
            'cancellation_actor' => Booking::CANCELLATION_ACTOR_OWNER,
        ]);
        $this->bookingFor($guest, $property, [
            'status' => Booking::STATUS_CANCELLED,
            'cancellation_actor' => Booking::CANCELLATION_ACTOR_GUEST,
        ]);

        $this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/dashboard/overview')
            ->assertOk()
            ->assertJsonPath('data.bookings.pending_bookings_count', 1)
            ->assertJsonPath('data.bookings.accepted_bookings_count', 1)
            ->assertJsonPath('data.bookings.completed_bookings_count', 1)
            ->assertJsonPath('data.bookings.cancelled_bookings_count', 2)
            ->assertJsonPath('data.bookings.rejected_bookings_count', 1)
            ->assertJsonPath('data.bookings.owner_cancelled_bookings_count', 1)
            ->assertJsonPath('data.bookings.guest_cancelled_bookings_count', 1);
    }

    public function test_moderation_counts_reviews_reports_and_unresolved_records(): void
    {
        $admin = $this->admin();
        $owner = User::factory()->create(['role' => 'owner']);
        $guest = User::factory()->create(['role' => 'guest']);
        $property = $this->propertyFor($owner);
        $booking = $this->bookingFor($guest, $property);

        $this->reportFor($guest, $property, $booking, ['status' => Report::STATUS_PENDING]);
        $this->reportFor($guest, $property, $booking, ['status' => Report::STATUS_REVIEWED]);
        $this->reportFor($guest, $property, $booking, ['status' => Report::STATUS_RESOLVED]);
        $this->reportFor($guest, $property, $booking, ['status' => Report::STATUS_REJECTED]);
        $this->reviewFor($guest, $property, $booking, ['status' => Review::STATUS_PUBLISHED]);
        $this->reviewFor($guest, $property, $this->bookingFor($guest, $property), ['status' => Review::STATUS_REJECTED]);
        $this->reviewFor($guest, $property, $this->bookingFor($guest, $property), [
            'status' => Review::STATUS_PENDING_REVIEW,
            'risk_score' => config('reviews.risk.high_risk_threshold'),
            'published_at' => null,
        ]);

        $this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/dashboard/overview')
            ->assertOk()
            ->assertJsonPath('data.moderation.pending_reports_count', 1)
            ->assertJsonPath('data.moderation.reviewed_reports_count', 1)
            ->assertJsonPath('data.moderation.unresolved_reports_count', 2)
            ->assertJsonPath('data.moderation.pending_reviews_count', 1)
            ->assertJsonPath('data.moderation.rejected_reviews_count', 1)
            ->assertJsonPath('data.moderation.published_reviews_count', 1)
            ->assertJsonPath('data.moderation.high_risk_reviews_count', 1);
    }

    public function test_serious_report_signals_and_alerts_are_returned(): void
    {
        $admin = $this->admin();
        $owner = User::factory()->create(['role' => 'owner']);
        $guest = User::factory()->create(['role' => 'guest']);
        $property = $this->propertyFor($owner);
        $booking = $this->bookingFor($guest, $property);

        $this->reportFor($guest, $property, $booking, [
            'status' => Report::STATUS_PENDING,
            'category' => Report::CATEGORY_UNSAFE_PROPERTY,
            'severity' => Report::SEVERITY_HIGH,
        ]);
        $this->reportFor($guest, $property, $booking, [
            'status' => Report::STATUS_RESOLVED,
            'category' => Report::CATEGORY_SCAM_OR_FRAUD,
            'severity' => Report::SEVERITY_CRITICAL,
        ]);
        $this->reviewFor($guest, $property, $booking, [
            'status' => Review::STATUS_PENDING_REVIEW,
            'risk_score' => config('reviews.risk.high_risk_threshold'),
            'published_at' => null,
        ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/dashboard/overview')
            ->assertOk()
            ->assertJsonPath('data.trust_and_safety.properties_with_unresolved_reports_count', 1)
            ->assertJsonPath('data.trust_and_safety.properties_with_serious_report_signals_count', 1);

        $alertTypes = collect($response->json('data.alerts'))->pluck('type');
        $this->assertTrue($alertTypes->contains('pending_reports'));
        $this->assertTrue($alertTypes->contains('pending_reviews'));
        $this->assertTrue($alertTypes->contains('high_risk_reviews'));
        $this->assertTrue($alertTypes->contains('serious_report_signals'));
    }

    public function test_trust_badge_count_uses_published_reviews_only(): void
    {
        $admin = $this->admin();
        $owner = User::factory()->create(['role' => 'owner']);
        $guest = User::factory()->create(['role' => 'guest']);
        $trustedProperty = $this->propertyFor($owner, ['title' => 'Trusted Suite']);
        $formingProperty = $this->propertyFor($owner, ['title' => 'Still Forming']);

        for ($i = 0; $i < 10; $i++) {
            $this->reviewFor($guest, $trustedProperty, $this->bookingFor($guest, $trustedProperty), [
                'rating' => 5,
                'status' => Review::STATUS_PUBLISHED,
            ]);
        }

        for ($i = 0; $i < 10; $i++) {
            $this->reviewFor($guest, $formingProperty, $this->bookingFor($guest, $formingProperty), [
                'rating' => 5,
                'status' => Review::STATUS_PENDING_REVIEW,
                'published_at' => null,
            ]);
        }

        $this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/dashboard/overview')
            ->assertOk()
            ->assertJsonPath('data.trust_and_safety.properties_with_trust_badge_count', 1);
    }

    public function test_privacy_sensitive_fields_are_not_exposed(): void
    {
        $admin = $this->admin();
        $owner = User::factory()->create(['role' => 'owner', 'email' => 'owner-private@example.test']);
        $guest = User::factory()->create(['role' => 'guest', 'email' => 'guest-private@example.test']);
        $property = $this->propertyFor($owner);
        $booking = $this->bookingFor($guest, $property);
        $this->reportFor($guest, $property, $booking, ['description' => 'Private report body.']);
        $this->reviewFor($guest, $property, $booking, [
            'comment' => 'Private review text.',
            'ip_hash' => 'secret-ip-hash',
            'user_agent_hash' => 'secret-agent-hash',
        ]);

        $json = $this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/dashboard/overview')
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('owner-private@example.test', $json);
        $this->assertStringNotContainsString('guest-private@example.test', $json);
        $this->assertStringNotContainsString('email', $json);
        $this->assertStringNotContainsString('phone', $json);
        $this->assertStringNotContainsString('ip_hash', $json);
        $this->assertStringNotContainsString('user_agent_hash', $json);
        $this->assertStringNotContainsString('secret-ip-hash', $json);
        $this->assertStringNotContainsString('secret-agent-hash', $json);
        $this->assertStringNotContainsString('Private report body.', $json);
        $this->assertStringNotContainsString('Private review text.', $json);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
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
            'start_date' => now()->subDays(8)->toDateString(),
            'end_date' => now()->subDays(5)->toDateString(),
            'total_price' => 500,
            'status' => Booking::STATUS_ACCEPTED,
        ], $attributes));
    }

    private function reportFor(User $reporter, Property $property, Booking $booking, array $attributes = []): Report
    {
        return Report::create(array_merge([
            'reporter_user_id' => $reporter->id,
            'property_id' => $property->id,
            'booking_id' => $booking->id,
            'reported_user_id' => $property->user_id,
            'category' => Report::CATEGORY_HOST_ISSUE,
            'description' => 'A report description.',
            'status' => Report::STATUS_PENDING,
            'severity' => Report::SEVERITY_NORMAL,
        ], $attributes));
    }

    private function reviewFor(User $user, Property $property, Booking $booking, array $attributes = []): Review
    {
        $payload = array_merge([
            'rating' => 5,
            'comment' => 'A verified stay review.',
            'status' => Review::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
            'risk_score' => 0,
            'risk_reasons' => [],
            'ip_hash' => hash('sha256', '127.0.0.1'),
            'user_agent_hash' => hash('sha256', 'Test Browser'),
        ], $attributes);

        $review = new Review([
            'user_id' => $user->id,
            'property_id' => $property->id,
            'booking_id' => $booking->id,
            'rating' => $payload['rating'],
            'comment' => $payload['comment'],
        ]);

        $review->forceFill([
            'status' => $payload['status'],
            'published_at' => $payload['published_at'],
            'risk_score' => $payload['risk_score'],
            'risk_reasons' => $payload['risk_reasons'],
            'ip_hash' => $payload['ip_hash'],
            'user_agent_hash' => $payload['user_agent_hash'],
        ])->save();

        return $review;
    }
}
