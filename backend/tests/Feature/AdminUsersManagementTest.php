<?php

namespace Tests\Feature;

use App\Models\AnalyticsEvent;
use App\Models\Booking;
use App\Models\Property;
use App\Models\Report;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminUsersManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_unauthenticated_user_cannot_access_admin_users_index(): void
    {
        $this->getJson('/api/admin/users')->assertUnauthorized();
    }

    public function test_normal_user_cannot_access_admin_users_index(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this
            ->actingAs($user, 'sanctum')
            ->getJson('/api/admin/users')
            ->assertForbidden()
            ->assertJsonPath('message', 'This action is unauthorized.');
    }

    public function test_admin_can_list_users_with_pagination(): void
    {
        $admin = $this->admin();
        User::factory()->count(3)->create();

        $this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/users?per_page=2')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'email',
                        'role',
                        'is_demo_user',
                        'counts',
                    ],
                ],
                'links',
                'meta',
            ])
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 4);
    }

    public function test_admin_can_view_one_user_with_safe_detail_sections(): void
    {
        $admin = $this->admin();
        $owner = User::factory()->create(['role' => 'owner']);
        $guest = User::factory()->create([
            'name' => 'Detail Guest',
            'email' => 'detail.guest@example.test',
            'registered_country_code' => 'MA',
            'registered_country_name' => 'Morocco',
            'registered_region_name' => 'Casablanca-Settat',
            'registered_city_name' => 'Casablanca',
            'last_seen_country_code' => 'FR',
            'last_seen_country_name' => 'France',
            'last_seen_region_name' => 'Ile-de-France',
            'last_seen_city_name' => 'Paris',
            'last_seen_at' => now(),
        ]);
        $property = $this->propertyFor($owner, ['title' => 'Safe Suite']);
        $booking = $this->bookingFor($guest, $property);
        $this->reviewFor($guest, $property, $booking);
        $this->reportFor($guest, $property, $booking, ['description' => 'Private report body should not leak.']);
        $this->analyticsEventFor($guest, AnalyticsEvent::TYPE_USER_LOGGED_IN, [
            'ip_hash' => 'private-ip-hash',
            'user_agent_hash' => 'private-agent-hash',
        ]);

        $json = $this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/users/'.$guest->id)
            ->assertOk()
            ->assertJsonPath('data.name', 'Detail Guest')
            ->assertJsonPath('data.registered_country_code', 'MA')
            ->assertJsonPath('data.last_seen_city_name', 'Paris')
            ->assertJsonPath('data.counts.bookings_count', 1)
            ->assertJsonPath('data.counts.reviews_count', 1)
            ->assertJsonPath('data.counts.reports_count', 1)
            ->assertJsonPath('data.counts.analytics_events_count', 1)
            ->assertJsonPath('data.recent_bookings.0.property_title', 'Safe Suite')
            ->assertJsonPath('data.recent_reviews.0.rating', 5)
            ->assertJsonPath('data.recent_reports.0.category', Report::CATEGORY_HOST_ISSUE)
            ->assertJsonPath('data.recent_analytics_activity.0.event_type', AnalyticsEvent::TYPE_USER_LOGGED_IN)
            ->getContent();

        $this->assertStringNotContainsString('Private report body should not leak.', $json);
        $this->assertStringNotContainsString('private-ip-hash', $json);
        $this->assertStringNotContainsString('private-agent-hash', $json);
    }

    public function test_search_by_name_and_email_works(): void
    {
        $admin = $this->admin();
        User::factory()->create(['name' => 'Mouna Searchable', 'email' => 'mouna@example.test']);
        User::factory()->create(['name' => 'Houssen', 'email' => 'houssen.special@example.test']);
        User::factory()->create(['name' => 'Other User', 'email' => 'other@example.test']);

        $nameResults = $this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/users?search=Mouna')
            ->assertOk()
            ->json('data');

        $emailResults = $this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/users?search=houssen.special')
            ->assertOk()
            ->json('data');

        $this->assertSame(['Mouna Searchable'], collect($nameResults)->pluck('name')->all());
        $this->assertSame(['Houssen'], collect($emailResults)->pluck('name')->all());
    }

    public function test_role_country_city_and_boolean_filters_work(): void
    {
        $admin = $this->admin();
        $owner = User::factory()->create([
            'role' => 'owner',
            'registered_country_code' => 'MA',
            'registered_city_name' => 'Casablanca',
        ]);
        $guestWithBooking = User::factory()->create([
            'role' => 'user',
            'last_seen_country_code' => 'FR',
            'last_seen_city_name' => 'Paris',
        ]);
        $plainUser = User::factory()->create(['role' => 'user']);
        $property = $this->propertyFor($owner);
        $this->bookingFor($guestWithBooking, $property);

        $this->assertNames('/api/admin/users?role=owner', $admin, [$owner->name]);
        $this->assertNames('/api/admin/users?country_code=MA', $admin, [$owner->name]);
        $this->assertNames('/api/admin/users?city=Paris', $admin, [$guestWithBooking->name]);
        $this->assertNames('/api/admin/users?has_properties=true', $admin, [$owner->name]);
        $this->assertNames('/api/admin/users?has_bookings=true', $admin, [$guestWithBooking->name]);

        $withoutBookings = collect($this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/users?has_bookings=false')
            ->assertOk()
            ->json('data'))->pluck('id');

        $this->assertTrue($withoutBookings->contains($plainUser->id));
        $this->assertFalse($withoutBookings->contains($guestWithBooking->id));
    }

    public function test_demo_filters_and_include_demo_false_work(): void
    {
        $this->artisan('analytics:seed-demo-geography')->assertSuccessful();
        $admin = $this->admin();
        User::factory()->create(['email' => 'real.user@example.test']);

        $demoResults = $this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/users?demo=true&include_demo=true')
            ->assertOk()
            ->assertJsonPath('meta.demo_data.available', true)
            ->json('data');

        $realOnlyResults = $this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/users?include_demo=false&per_page=50')
            ->assertOk()
            ->assertJsonPath('meta.demo_data.included', false)
            ->json('data');

        $this->assertNotEmpty($demoResults);
        $this->assertTrue(collect($demoResults)->every(fn ($user) => $user['is_demo_user'] === true));
        $this->assertFalse(collect($realOnlyResults)->contains(fn ($user) => $user['is_demo_user'] === true));
    }

    public function test_sorting_options_work(): void
    {
        Carbon::setTestNow('2026-08-02 12:00:00');
        $admin = $this->admin(['name' => 'Zzz Admin']);
        $admin->forceFill(['created_at' => now()->subDays(10), 'updated_at' => now()->subDays(10)])->save();
        $alpha = User::factory()->create(['name' => 'Alpha', 'created_at' => now()->subDays(2)]);
        $zulu = User::factory()->create(['name' => 'Zulu', 'created_at' => now()->subDay()]);
        $recent = User::factory()->create(['name' => 'Recent', 'created_at' => now()]);

        $oldest = collect($this->actingAs($admin, 'sanctum')->getJson('/api/admin/users?sort=oldest')->json('data'))->pluck('id');
        $newest = collect($this->actingAs($admin, 'sanctum')->getJson('/api/admin/users?sort=newest')->json('data'))->pluck('id');
        $byName = collect($this->actingAs($admin, 'sanctum')->getJson('/api/admin/users?sort=name')->json('data'))->pluck('id');

        $this->assertLessThan($oldest->search($zulu->id), $oldest->search($alpha->id));
        $this->assertSame($recent->id, $newest->first());
        $this->assertSame($alpha->id, $byName->first());
        $this->assertTrue($byName->contains($zulu->id));
    }

    public function test_sorting_by_counts_works(): void
    {
        $admin = $this->admin();
        $ownerA = User::factory()->create(['name' => 'One Property']);
        $ownerB = User::factory()->create(['name' => 'Two Properties']);
        $guestA = User::factory()->create(['name' => 'One Booking']);
        $guestB = User::factory()->create(['name' => 'Two Bookings']);

        $propertyA = $this->propertyFor($ownerA);
        $propertyB = $this->propertyFor($ownerB);
        $this->propertyFor($ownerB, ['title' => 'Second Suite']);
        $this->bookingFor($guestA, $propertyA);
        $this->bookingFor($guestB, $propertyA);
        $this->bookingFor($guestB, $propertyB);

        $propertiesSorted = collect($this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/users?sort=properties_count')
            ->assertOk()
            ->json('data'));

        $bookingsSorted = collect($this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/users?sort=bookings_count')
            ->assertOk()
            ->json('data'));

        $this->assertSame('Two Properties', $propertiesSorted->first()['name']);
        $this->assertSame('Two Bookings', $bookingsSorted->first()['name']);
    }

    public function test_missing_reports_and_analytics_tables_are_handled_defensively(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();

        Schema::dropIfExists('reports_backup_for_admin_users_test');
        Schema::dropIfExists('analytics_events_backup_for_admin_users_test');
        Schema::rename('reports', 'reports_backup_for_admin_users_test');
        Schema::rename('analytics_events', 'analytics_events_backup_for_admin_users_test');

        try {
            $this
                ->actingAs($admin, 'sanctum')
                ->getJson('/api/admin/users/'.$user->id)
                ->assertOk()
                ->assertJsonPath('data.counts.reports_count', 0)
                ->assertJsonPath('data.counts.analytics_events_count', 0)
                ->assertJsonPath('data.recent_reports', [])
                ->assertJsonPath('data.recent_analytics_activity', []);
        } finally {
            Schema::rename('reports_backup_for_admin_users_test', 'reports');
            Schema::rename('analytics_events_backup_for_admin_users_test', 'analytics_events');
        }
    }

    public function test_admin_users_responses_do_not_expose_private_fields(): void
    {
        $admin = $this->admin();
        $owner = User::factory()->create(['email' => 'nested-owner@example.test']);
        $user = User::factory()->create([
            'password' => 'secret-password',
            'remember_token' => 'remember-secret',
            'phone' => '555-PRIVATE',
        ]);
        $property = $this->propertyFor($owner);
        $booking = $this->bookingFor($user, $property);
        $this->reportFor($user, $property, $booking, ['description' => 'Secret report description']);
        $this->analyticsEventFor($user, AnalyticsEvent::TYPE_USER_LOGGED_IN, [
            'ip_hash' => 'secret-ip-hash',
            'user_agent_hash' => 'secret-agent-hash',
        ]);

        $json = $this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/users/'.$user->id)
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('password', $json);
        $this->assertStringNotContainsString('remember_token', $json);
        $this->assertStringNotContainsString('remember-secret', $json);
        $this->assertStringNotContainsString('token', $json);
        $this->assertStringNotContainsString('555-PRIVATE', $json);
        $this->assertStringNotContainsString('ip_hash', $json);
        $this->assertStringNotContainsString('user_agent_hash', $json);
        $this->assertStringNotContainsString('secret-ip-hash', $json);
        $this->assertStringNotContainsString('secret-agent-hash', $json);
        $this->assertStringNotContainsString('Secret report description', $json);
        $this->assertStringNotContainsString('nested-owner@example.test', $json);
    }

    private function admin(array $attributes = []): User
    {
        return User::factory()->create(array_merge(['role' => 'admin'], $attributes));
    }

    private function assertNames(string $url, User $admin, array $expectedNames): void
    {
        $names = collect($this
            ->actingAs($admin, 'sanctum')
            ->getJson($url)
            ->assertOk()
            ->json('data'))->pluck('name')->all();

        $this->assertSame($expectedNames, $names);
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
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(7)->toDateString(),
            'total_price' => 500,
            'status' => Booking::STATUS_ACCEPTED,
        ], $attributes));
    }

    private function reviewFor(User $user, Property $property, Booking $booking, array $attributes = []): Review
    {
        $review = new Review([
            'user_id' => $user->id,
            'property_id' => $property->id,
            'booking_id' => $booking->id,
            'rating' => $attributes['rating'] ?? 5,
            'comment' => $attributes['comment'] ?? 'A verified stay review.',
        ]);

        $review->forceFill([
            'status' => $attributes['status'] ?? Review::STATUS_PUBLISHED,
            'published_at' => $attributes['published_at'] ?? now(),
            'risk_score' => $attributes['risk_score'] ?? 0,
            'risk_reasons' => $attributes['risk_reasons'] ?? [],
            'ip_hash' => $attributes['ip_hash'] ?? hash('sha256', '127.0.0.1'),
            'user_agent_hash' => $attributes['user_agent_hash'] ?? hash('sha256', 'Test Browser'),
        ])->save();

        return $review;
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

    private function analyticsEventFor(User $user, string $eventType, array $attributes = []): AnalyticsEvent
    {
        return AnalyticsEvent::create(array_merge([
            'user_id' => $user->id,
            'event_type' => $eventType,
            'country_code' => 'MA',
            'country_name' => 'Morocco',
            'country_source' => 'test',
            'region_name' => 'Casablanca-Settat',
            'city_name' => 'Casablanca',
            'metadata' => ['source' => 'test'],
            'occurred_at' => now(),
        ], $attributes));
    }
}
