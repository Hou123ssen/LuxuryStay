<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Image;
use App\Models\Property;
use App\Models\Report;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminPropertiesManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_unauthenticated_user_cannot_access_admin_properties_index(): void
    {
        $this->getJson('/api/admin/properties')->assertUnauthorized();
    }

    public function test_normal_user_cannot_access_admin_properties_index(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this
            ->actingAs($user, 'sanctum')
            ->getJson('/api/admin/properties')
            ->assertForbidden()
            ->assertJsonPath('message', 'This action is unauthorized.');
    }

    public function test_admin_can_list_properties_with_pagination_and_safe_fields(): void
    {
        $admin = $this->admin();
        $owner = User::factory()->create(['role' => 'owner']);
        $property = $this->propertyFor($owner, [
            'title' => 'Atlas Penthouse',
            'description' => str_repeat('Elegant private description. ', 12),
        ]);
        Image::create(['property_id' => $property->id, 'path' => 'properties/atlas.jpg']);

        $this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/properties?per_page=1')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'description_excerpt',
                        'type',
                        'price_per_night',
                        'city',
                        'status',
                        'owner' => ['id', 'name', 'email', 'role'],
                        'counts' => ['bookings_count', 'reviews_count', 'reports_count', 'images_count'],
                        'rating' => ['average_rating', 'reviews_count', 'rating_state', 'rating_label', 'trust_badge', 'trust_label'],
                        'owner_reliability',
                    ],
                ],
                'links',
                'meta',
            ])
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.title', 'Atlas Penthouse')
            ->assertJsonPath('data.0.counts.images_count', 1);
    }

    public function test_admin_can_view_one_property_with_safe_detail_sections(): void
    {
        $admin = $this->admin();
        $owner = User::factory()->create(['role' => 'owner', 'name' => 'Owner Safe']);
        $guest = User::factory()->create(['name' => 'Guest Safe']);
        $property = $this->propertyFor($owner, ['title' => 'Detail Riad']);
        $booking = $this->bookingFor($guest, $property);
        $this->reviewFor($guest, $property, $booking, [
            'comment' => 'Private review text should not leak.',
        ]);
        $this->reportFor($guest, $property, $booking, [
            'description' => 'Private report description should not leak.',
        ]);
        Image::create(['property_id' => $property->id, 'path' => 'properties/detail.jpg']);

        $json = $this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/properties/'.$property->id)
            ->assertOk()
            ->assertJsonPath('data.title', 'Detail Riad')
            ->assertJsonPath('data.owner.name', 'Owner Safe')
            ->assertJsonPath('data.recent_bookings.0.guest_name', 'Guest Safe')
            ->assertJsonPath('data.recent_reviews.0.rating', 5)
            ->assertJsonPath('data.recent_reviews.0.status', Review::STATUS_PUBLISHED)
            ->assertJsonPath('data.recent_reports.0.category', Report::CATEGORY_HOST_ISSUE)
            ->assertJsonPath('data.images.0.id', 1)
            ->getContent();

        $this->assertStringNotContainsString('Private report description should not leak.', $json);
        $this->assertStringNotContainsString('Private review text should not leak.', $json);
    }

    public function test_search_and_basic_filters_work(): void
    {
        $admin = $this->admin();
        $ownerA = User::factory()->create(['name' => 'Mouna Owner', 'email' => 'mouna.owner@example.test']);
        $ownerB = User::factory()->create(['name' => 'Other Owner', 'email' => 'other.owner@example.test']);
        $guest = User::factory()->create();
        $riad = $this->propertyFor($ownerA, ['title' => 'Mouna Riad', 'city' => 'Marrakech', 'price_per_night' => 120]);
        $loft = $this->propertyFor($ownerB, ['title' => 'Ocean Loft', 'city' => 'Casablanca', 'price_per_night' => 600]);
        $booking = $this->bookingFor($guest, $riad);
        $this->reviewFor($guest, $riad, $booking);
        $this->reportFor($guest, $riad, $booking);

        $this->assertTitles('/api/admin/properties?search=Mouna', $admin, ['Mouna Riad']);
        $this->assertTitles('/api/admin/properties?search=other.owner', $admin, ['Ocean Loft']);
        $this->assertTitles('/api/admin/properties?owner_id='.$ownerA->id, $admin, ['Mouna Riad']);
        $this->assertTitles('/api/admin/properties?city=Casa', $admin, ['Ocean Loft']);
        $this->assertTitles('/api/admin/properties?has_bookings=true', $admin, ['Mouna Riad']);
        $this->assertTitles('/api/admin/properties?has_reviews=true', $admin, ['Mouna Riad']);
        $this->assertTitles('/api/admin/properties?has_reports=true', $admin, ['Mouna Riad']);
        $this->assertTitles('/api/admin/properties?min_price=500', $admin, ['Ocean Loft']);
        $this->assertTitles('/api/admin/properties?max_price=200', $admin, ['Mouna Riad']);

        $withoutBookings = collect($this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/properties?has_bookings=false')
            ->assertOk()
            ->json('data'))->pluck('id');

        $this->assertFalse($withoutBookings->contains($riad->id));
        $this->assertTrue($withoutBookings->contains($loft->id));
    }

    public function test_sorting_options_work(): void
    {
        Carbon::setTestNow('2026-08-02 12:00:00');
        $admin = $this->admin();
        $owner = User::factory()->create(['role' => 'owner']);
        $guest = User::factory()->create();
        $alpha = $this->propertyFor($owner, ['title' => 'Alpha', 'price_per_night' => 100, 'created_at' => now()->subDays(3)]);
        $zulu = $this->propertyFor($owner, ['title' => 'Zulu', 'price_per_night' => 500, 'created_at' => now()->subDays(2)]);
        $recent = $this->propertyFor($owner, ['title' => 'Recent', 'price_per_night' => 300, 'created_at' => now()]);

        $booking = $this->bookingFor($guest, $alpha);
        $this->bookingFor(User::factory()->create(), $alpha);
        $this->bookingFor($guest, $zulu);
        $this->reviewFor($guest, $alpha, $booking);

        $this->assertFirstTitle('/api/admin/properties?sort=newest', $admin, 'Recent');
        $this->assertFirstTitle('/api/admin/properties?sort=oldest', $admin, 'Alpha');
        $this->assertFirstTitle('/api/admin/properties?sort=title', $admin, 'Alpha');
        $this->assertFirstTitle('/api/admin/properties?sort=price_low', $admin, 'Alpha');
        $this->assertFirstTitle('/api/admin/properties?sort=price_high', $admin, 'Zulu');
        $this->assertFirstTitle('/api/admin/properties?sort=bookings_count', $admin, 'Alpha');
        $this->assertFirstTitle('/api/admin/properties?sort=reviews_count', $admin, 'Alpha');
        $this->assertFirstTitle('/api/admin/properties?sort=rating', $admin, 'Alpha');
    }

    public function test_property_resource_privacy(): void
    {
        $admin = $this->admin();
        $owner = User::factory()->create([
            'role' => 'owner',
            'password' => 'secret-password',
            'remember_token' => 'remember-secret',
        ]);
        $guest = User::factory()->create();
        $property = $this->propertyFor($owner, ['address' => 'Exact private villa address']);
        $booking = $this->bookingFor($guest, $property);
        $this->reviewFor($guest, $property, $booking, [
            'comment' => 'Private review comment',
            'ip_hash' => 'secret-ip-hash',
            'user_agent_hash' => 'secret-agent-hash',
        ]);
        $this->reportFor($guest, $property, $booking, [
            'description' => 'Private report body',
            'admin_notes' => 'Internal admin notes',
        ]);

        $json = $this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/properties/'.$property->id)
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('password', $json);
        $this->assertStringNotContainsString('remember_token', $json);
        $this->assertStringNotContainsString('remember-secret', $json);
        $this->assertStringNotContainsString('token', $json);
        $this->assertStringNotContainsString('Exact private villa address', $json);
        $this->assertStringNotContainsString('Private report body', $json);
        $this->assertStringNotContainsString('Internal admin notes', $json);
        $this->assertStringNotContainsString('Private review comment', $json);
        $this->assertStringNotContainsString('ip_hash', $json);
        $this->assertStringNotContainsString('user_agent_hash', $json);
        $this->assertStringNotContainsString('secret-ip-hash', $json);
        $this->assertStringNotContainsString('secret-agent-hash', $json);
        $this->assertStringNotContainsString('ranking_score', $json);
        $this->assertStringNotContainsString('public_rating', $json);
    }

    public function test_missing_reports_and_images_tables_are_handled_defensively(): void
    {
        $admin = $this->admin();
        $owner = User::factory()->create(['role' => 'owner']);
        $property = $this->propertyFor($owner);

        Schema::dropIfExists('reports_backup_for_admin_properties_test');
        Schema::dropIfExists('images_backup_for_admin_properties_test');
        Schema::rename('reports', 'reports_backup_for_admin_properties_test');
        Schema::rename('images', 'images_backup_for_admin_properties_test');

        try {
            $this
                ->actingAs($admin, 'sanctum')
                ->getJson('/api/admin/properties/'.$property->id)
                ->assertOk()
                ->assertJsonPath('data.counts.reports_count', 0)
                ->assertJsonPath('data.counts.images_count', 0)
                ->assertJsonPath('data.recent_reports', [])
                ->assertJsonPath('data.images', []);
        } finally {
            Schema::rename('reports_backup_for_admin_properties_test', 'reports');
            Schema::rename('images_backup_for_admin_properties_test', 'images');
        }
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function assertTitles(string $url, User $admin, array $expectedTitles): void
    {
        $titles = collect($this
            ->actingAs($admin, 'sanctum')
            ->getJson($url)
            ->assertOk()
            ->json('data'))->pluck('title')->all();

        $this->assertSame($expectedTitles, $titles);
    }

    private function assertFirstTitle(string $url, User $admin, string $expectedTitle): void
    {
        $this->assertSame($expectedTitle, $this
            ->actingAs($admin, 'sanctum')
            ->getJson($url)
            ->assertOk()
            ->json('data.0.title'));
    }

    private function propertyFor(User $user, array $attributes = []): Property
    {
        $property = Property::create(array_merge([
            'user_id' => $user->id,
            'title' => 'Owner Suite',
            'description' => 'A calm test property.',
            'type' => 'apartment',
            'price_per_night' => 250,
            'city' => 'Marrakech',
            'address' => 'Medina',
        ], collect($attributes)->except(['created_at', 'updated_at'])->all()));

        if (array_key_exists('created_at', $attributes) || array_key_exists('updated_at', $attributes)) {
            $property->forceFill([
                'created_at' => $attributes['created_at'] ?? $property->created_at,
                'updated_at' => $attributes['updated_at'] ?? $attributes['created_at'] ?? $property->updated_at,
            ])->save();
        }

        return $property;
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
}
