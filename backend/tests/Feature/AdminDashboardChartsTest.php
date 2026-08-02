<?php

namespace Tests\Feature;

use App\Models\AnalyticsEvent;
use App\Models\Booking;
use App\Models\Property;
use App\Models\Report;
use App\Models\Review;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminDashboardChartsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_unauthenticated_user_cannot_access_charts(): void
    {
        $this->getJson('/api/admin/dashboard/charts')->assertUnauthorized();
    }

    public function test_non_admin_user_cannot_access_charts(): void
    {
        $user = User::factory()->create(['role' => 'guest']);

        $this
            ->actingAs($user, 'sanctum')
            ->getJson('/api/admin/dashboard/charts')
            ->assertForbidden()
            ->assertJsonPath('message', 'This action is unauthorized.');
    }

    public function test_charts_return_expected_structure_and_default_period(): void
    {
        Carbon::setTestNow('2026-08-02 12:00:00');

        $admin = $this->admin();

        $this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/dashboard/charts')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'period' => ['days', 'group_by', 'start_date', 'end_date'],
                    'series' => ['registrations', 'logins', 'bookings', 'reviews', 'reports'],
                    'breakdowns' => ['bookings_by_status', 'reviews_by_status', 'reports_by_status'],
                    'totals' => ['registrations', 'logins', 'bookings', 'reviews', 'reports'],
                ],
            ])
            ->assertJsonPath('data.period.days', '30')
            ->assertJsonPath('data.period.group_by', 'day')
            ->assertJsonPath('data.period.start_date', '2026-07-04')
            ->assertJsonPath('data.period.end_date', '2026-08-02');
    }

    public function test_daily_series_count_registrations_logins_bookings_reviews_and_reports(): void
    {
        Carbon::setTestNow('2026-08-02 12:00:00');

        $admin = $this->admin();
        $owner = User::factory()->create(['role' => 'owner']);
        $guest = User::factory()->create(['role' => 'guest']);
        $property = $this->propertyFor($owner);
        $booking = $this->bookingFor($guest, $property, [
            'status' => Booking::STATUS_ACCEPTED,
            'created_at' => '2026-08-01 10:00:00',
        ]);

        $this->analyticsEventFor($guest, AnalyticsEvent::TYPE_USER_REGISTERED, '2026-08-01 08:00:00');
        $this->analyticsEventFor($owner, AnalyticsEvent::TYPE_USER_REGISTERED, '2026-08-01 09:00:00');
        $this->analyticsEventFor($guest, AnalyticsEvent::TYPE_USER_LOGGED_IN, '2026-08-01 11:00:00');
        $this->reviewFor($guest, $property, $booking, [
            'status' => Review::STATUS_PUBLISHED,
            'created_at' => '2026-08-01 12:00:00',
        ]);
        $this->reportFor($guest, $property, $booking, [
            'status' => Report::STATUS_PENDING,
            'created_at' => '2026-08-01 13:00:00',
        ]);

        $data = $this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/dashboard/charts?days=7')
            ->assertOk()
            ->json('data');

        $this->assertCount(7, $data['series']['registrations']);
        $this->assertSame(2, $this->countFor($data['series']['registrations'], '2026-08-01'));
        $this->assertSame(1, $this->countFor($data['series']['logins'], '2026-08-01'));
        $this->assertSame(1, $this->countFor($data['series']['bookings'], '2026-08-01'));
        $this->assertSame(1, $this->countFor($data['series']['reviews'], '2026-08-01'));
        $this->assertSame(1, $this->countFor($data['series']['reports'], '2026-08-01'));
        $this->assertSame(0, $this->countFor($data['series']['bookings'], '2026-07-31'));
        $this->assertSame(2, $data['totals']['registrations']);
        $this->assertSame(1, $data['totals']['logins']);
        $this->assertSame(1, $data['totals']['bookings']);
        $this->assertSame(1, $data['totals']['reviews']);
        $this->assertSame(1, $data['totals']['reports']);
    }

    public function test_days_all_defaults_to_monthly_series(): void
    {
        Carbon::setTestNow('2026-08-02 12:00:00');

        $admin = $this->admin();
        $user = User::factory()->create(['role' => 'guest']);

        $this->analyticsEventFor($user, AnalyticsEvent::TYPE_USER_REGISTERED, '2026-05-10 10:00:00');
        $this->analyticsEventFor($user, AnalyticsEvent::TYPE_USER_LOGGED_IN, '2026-08-01 10:00:00');

        $data = $this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/dashboard/charts?days=all')
            ->assertOk()
            ->assertJsonPath('data.period.days', 'all')
            ->assertJsonPath('data.period.group_by', 'month')
            ->json('data');

        $this->assertSame(['2026-05', '2026-06', '2026-07', '2026-08'], collect($data['series']['registrations'])->pluck('date')->all());
        $this->assertSame(1, $this->countFor($data['series']['registrations'], '2026-05'));
        $this->assertSame(1, $this->countFor($data['series']['logins'], '2026-08'));
    }

    public function test_invalid_period_options_fall_back_to_safe_defaults(): void
    {
        Carbon::setTestNow('2026-08-02 12:00:00');

        $admin = $this->admin();

        $this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/dashboard/charts?days=bad&period=bad&group_by=bad')
            ->assertOk()
            ->assertJsonPath('data.period.days', '30')
            ->assertJsonPath('data.period.group_by', 'day')
            ->assertJsonPath('data.period.start_date', '2026-07-04');
    }

    public function test_status_breakdowns_count_records_in_period(): void
    {
        Carbon::setTestNow('2026-08-02 12:00:00');

        $admin = $this->admin();
        $owner = User::factory()->create(['role' => 'owner']);
        $guest = User::factory()->create(['role' => 'guest']);
        $property = $this->propertyFor($owner);

        $pendingBooking = $this->bookingFor($guest, $property, [
            'status' => Booking::STATUS_PENDING,
            'created_at' => '2026-08-01 10:00:00',
        ]);
        $acceptedBooking = $this->bookingFor($guest, $property, [
            'status' => Booking::STATUS_ACCEPTED,
            'created_at' => '2026-08-01 11:00:00',
        ]);
        $this->reviewFor($guest, $property, $acceptedBooking, [
            'status' => Review::STATUS_PUBLISHED,
            'created_at' => '2026-08-01 12:00:00',
        ]);
        $this->reviewFor($guest, $property, $this->bookingFor($guest, $property, [
            'created_at' => '2026-06-01 10:00:00',
        ]), [
            'status' => Review::STATUS_REJECTED,
            'created_at' => '2026-08-01 13:00:00',
        ]);
        $this->reportFor($guest, $property, $pendingBooking, [
            'status' => Report::STATUS_PENDING,
            'created_at' => '2026-08-01 14:00:00',
        ]);
        $this->reportFor($guest, $property, $acceptedBooking, [
            'status' => Report::STATUS_RESOLVED,
            'created_at' => '2026-08-01 15:00:00',
        ]);

        $breakdowns = $this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/dashboard/charts?days=7')
            ->assertOk()
            ->json('data.breakdowns');

        $this->assertSame(1, $this->breakdownCount($breakdowns['bookings_by_status'], Booking::STATUS_PENDING));
        $this->assertSame(1, $this->breakdownCount($breakdowns['bookings_by_status'], Booking::STATUS_ACCEPTED));
        $this->assertSame(1, $this->breakdownCount($breakdowns['reviews_by_status'], Review::STATUS_PUBLISHED));
        $this->assertSame(1, $this->breakdownCount($breakdowns['reviews_by_status'], Review::STATUS_REJECTED));
        $this->assertSame(1, $this->breakdownCount($breakdowns['reports_by_status'], Report::STATUS_PENDING));
        $this->assertSame(1, $this->breakdownCount($breakdowns['reports_by_status'], Report::STATUS_RESOLVED));
    }

    public function test_charts_return_safe_zeroes_when_reports_table_is_missing(): void
    {
        Carbon::setTestNow('2026-08-02 12:00:00');

        $admin = $this->admin();

        Schema::dropIfExists('reports_backup_for_charts_test');
        Schema::rename('reports', 'reports_backup_for_charts_test');

        try {
            $this
                ->actingAs($admin, 'sanctum')
                ->getJson('/api/admin/dashboard/charts?days=7')
                ->assertOk()
                ->assertJsonPath('data.totals.reports', 0)
                ->assertJsonPath('data.breakdowns.reports_by_status', []);
        } finally {
            Schema::rename('reports_backup_for_charts_test', 'reports');
        }
    }

    public function test_charts_return_safe_series_when_analytics_events_table_is_missing(): void
    {
        Carbon::setTestNow('2026-08-02 12:00:00');

        $admin = $this->admin();

        Schema::dropIfExists('analytics_events_backup_for_charts_test');
        Schema::rename('analytics_events', 'analytics_events_backup_for_charts_test');

        try {
            $this
                ->actingAs($admin, 'sanctum')
                ->getJson('/api/admin/dashboard/charts?days=7')
                ->assertOk()
                ->assertJsonPath('data.period.days', '7')
                ->assertJsonPath('data.totals.logins', 0);
        } finally {
            Schema::rename('analytics_events_backup_for_charts_test', 'analytics_events');
        }
    }

    public function test_charts_do_not_expose_private_fields_or_content(): void
    {
        Carbon::setTestNow('2026-08-02 12:00:00');

        $admin = $this->admin();
        $owner = User::factory()->create(['role' => 'owner', 'email' => 'owner-secret@example.test']);
        $guest = User::factory()->create(['role' => 'guest', 'email' => 'guest-secret@example.test']);
        $property = $this->propertyFor($owner);
        $booking = $this->bookingFor($guest, $property, ['created_at' => '2026-08-01 10:00:00']);

        $this->analyticsEventFor($guest, AnalyticsEvent::TYPE_USER_LOGGED_IN, '2026-08-01 11:00:00', [
            'ip_hash' => 'secret-ip-hash',
            'user_agent_hash' => 'secret-agent-hash',
        ]);
        $this->reviewFor($guest, $property, $booking, [
            'comment' => 'Sensitive review body.',
            'ip_hash' => 'secret-review-ip-hash',
            'user_agent_hash' => 'secret-review-agent-hash',
            'created_at' => '2026-08-01 12:00:00',
        ]);
        $this->reportFor($guest, $property, $booking, [
            'description' => 'Sensitive report body.',
            'created_at' => '2026-08-01 13:00:00',
        ]);

        $json = $this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/dashboard/charts?days=7')
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('owner-secret@example.test', $json);
        $this->assertStringNotContainsString('guest-secret@example.test', $json);
        $this->assertStringNotContainsString('email', $json);
        $this->assertStringNotContainsString('phone', $json);
        $this->assertStringNotContainsString('ip_hash', $json);
        $this->assertStringNotContainsString('user_agent_hash', $json);
        $this->assertStringNotContainsString('secret-ip-hash', $json);
        $this->assertStringNotContainsString('secret-agent-hash', $json);
        $this->assertStringNotContainsString('secret-review-ip-hash', $json);
        $this->assertStringNotContainsString('secret-review-agent-hash', $json);
        $this->assertStringNotContainsString('Sensitive review body.', $json);
        $this->assertStringNotContainsString('Sensitive report body.', $json);
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
        $timestamps = collect($attributes)->only(['created_at', 'updated_at'])->all();
        $attributes = collect($attributes)->except(['created_at', 'updated_at'])->all();

        $booking = Booking::create(array_merge([
            'user_id' => $user->id,
            'property_id' => $property->id,
            'start_date' => now()->subDays(8)->toDateString(),
            'end_date' => now()->subDays(5)->toDateString(),
            'total_price' => 500,
            'status' => Booking::STATUS_ACCEPTED,
        ], $attributes));

        if ($timestamps !== []) {
            $booking->forceFill(array_merge([
                'updated_at' => $timestamps['created_at'] ?? now(),
            ], $timestamps))->save();
        }

        return $booking;
    }

    private function reportFor(User $reporter, Property $property, Booking $booking, array $attributes = []): Report
    {
        $timestamps = collect($attributes)->only(['created_at', 'updated_at'])->all();
        $attributes = collect($attributes)->except(['created_at', 'updated_at'])->all();

        $report = Report::create(array_merge([
            'reporter_user_id' => $reporter->id,
            'property_id' => $property->id,
            'booking_id' => $booking->id,
            'reported_user_id' => $property->user_id,
            'category' => Report::CATEGORY_HOST_ISSUE,
            'description' => 'A report description.',
            'status' => Report::STATUS_PENDING,
            'severity' => Report::SEVERITY_NORMAL,
        ], $attributes));

        if ($timestamps !== []) {
            $report->forceFill(array_merge([
                'updated_at' => $timestamps['created_at'] ?? now(),
            ], $timestamps))->save();
        }

        return $report;
    }

    private function reviewFor(User $user, Property $property, Booking $booking, array $attributes = []): Review
    {
        $timestamps = collect($attributes)->only(['created_at', 'updated_at'])->all();
        $attributes = collect($attributes)->except(['created_at', 'updated_at'])->all();

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

        if ($timestamps !== []) {
            $review->forceFill(array_merge([
                'updated_at' => $timestamps['created_at'] ?? now(),
            ], $timestamps))->save();
        }

        return $review;
    }

    private function analyticsEventFor(User $user, string $eventType, string $occurredAt, array $attributes = []): AnalyticsEvent
    {
        return AnalyticsEvent::create(array_merge([
            'user_id' => $user->id,
            'event_type' => $eventType,
            'country_code' => 'MA',
            'country_name' => 'Morocco',
            'country_source' => 'test',
            'region_name' => 'Casablanca-Settat',
            'city_name' => 'Casablanca',
            'metadata' => [],
            'occurred_at' => $occurredAt,
        ], $attributes));
    }

    private function countFor(array $series, string $date): int
    {
        return collect($series)->firstWhere('date', $date)['count'] ?? 0;
    }

    private function breakdownCount(array $breakdown, string $status): int
    {
        return collect($breakdown)->firstWhere('status', $status)['count'] ?? 0;
    }
}
