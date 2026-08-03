<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Property;
use App\Models\Report;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminBookingsManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_unauthenticated_user_cannot_access_admin_bookings_index(): void
    {
        $this->getJson('/api/admin/bookings')->assertUnauthorized();
    }

    public function test_normal_user_cannot_access_admin_bookings_index(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this
            ->actingAs($user, 'sanctum')
            ->getJson('/api/admin/bookings')
            ->assertForbidden()
            ->assertJsonPath('message', 'This action is unauthorized.');
    }

    public function test_admin_can_list_bookings_with_pagination_and_safe_fields(): void
    {
        $admin = $this->admin();
        $owner = User::factory()->create(['role' => 'owner', 'name' => 'Owner List']);
        $guest = User::factory()->create(['name' => 'Guest List']);
        $property = $this->propertyFor($owner, ['title' => 'List Riad', 'city' => 'Marrakech']);
        $booking = $this->bookingFor($guest, $property, [
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-13',
            'total_price' => 900,
        ]);
        $this->reviewFor($guest, $property, $booking);
        $this->reportFor($guest, $property, $booking);

        $this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/bookings?per_page=1')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'status',
                        'start_date',
                        'end_date',
                        'nights',
                        'total_price',
                        'guest' => ['id', 'name', 'email', 'role'],
                        'property' => ['id', 'title', 'city'],
                        'owner' => ['id', 'name', 'email', 'role'],
                        'signals' => ['has_review', 'reviews_count', 'reports_count'],
                    ],
                ],
                'links',
                'meta',
            ])
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.nights', 3)
            ->assertJsonPath('data.0.guest.name', 'Guest List')
            ->assertJsonPath('data.0.owner.name', 'Owner List')
            ->assertJsonPath('data.0.property.title', 'List Riad')
            ->assertJsonPath('data.0.signals.has_review', true)
            ->assertJsonPath('data.0.signals.reviews_count', 1)
            ->assertJsonPath('data.0.signals.reports_count', 1);
    }

    public function test_admin_can_view_one_booking_with_safe_detail_sections(): void
    {
        $admin = $this->admin();
        $owner = User::factory()->create(['role' => 'owner']);
        $guest = User::factory()->create(['name' => 'Detail Guest']);
        $property = $this->propertyFor($owner, ['title' => 'Detail Suite']);
        $booking = $this->bookingFor($guest, $property);
        $this->reviewFor($guest, $property, $booking, ['comment' => 'Private review text should not leak.']);
        $this->reportFor($guest, $property, $booking, ['description' => 'Private report description should not leak.']);

        $json = $this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/bookings/'.$booking->id)
            ->assertOk()
            ->assertJsonPath('data.id', $booking->id)
            ->assertJsonPath('data.guest.name', 'Detail Guest')
            ->assertJsonPath('data.property.title', 'Detail Suite')
            ->assertJsonPath('data.review.rating', 5)
            ->assertJsonPath('data.review.status', Review::STATUS_PUBLISHED)
            ->assertJsonPath('data.reports.0.category', Report::CATEGORY_HOST_ISSUE)
            ->assertJsonPath('data.payment', null)
            ->getContent();

        $this->assertStringNotContainsString('Private review text should not leak.', $json);
        $this->assertStringNotContainsString('Private report description should not leak.', $json);
    }

    public function test_search_and_filters_work(): void
    {
        $admin = $this->admin();
        $ownerA = User::factory()->create(['name' => 'Mouna Owner', 'email' => 'mouna.owner@example.test']);
        $ownerB = User::factory()->create(['name' => 'Other Owner', 'email' => 'other.owner@example.test']);
        $guestA = User::factory()->create(['name' => 'Draga Guest', 'email' => 'draga@example.test']);
        $guestB = User::factory()->create(['name' => 'Plain Guest', 'email' => 'plain@example.test']);
        $riad = $this->propertyFor($ownerA, ['title' => 'Atlas Riad', 'city' => 'Marrakech']);
        $loft = $this->propertyFor($ownerB, ['title' => 'Ocean Loft', 'city' => 'Casablanca']);
        $bookingA = $this->bookingFor($guestA, $riad, [
            'status' => Booking::STATUS_ACCEPTED,
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-15',
            'created_at' => '2026-07-01 10:00:00',
            'total_price' => 1000,
        ]);
        $bookingB = $this->bookingFor($guestB, $loft, [
            'status' => Booking::STATUS_PENDING,
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-12',
            'created_at' => '2026-07-20 10:00:00',
            'total_price' => 300,
        ]);
        $this->reviewFor($guestA, $riad, $bookingA);
        $this->reportFor($guestA, $riad, $bookingA);

        $this->assertBookingIds('/api/admin/bookings?search=Atlas', $admin, [$bookingA->id]);
        $this->assertBookingIds('/api/admin/bookings?search=draga@example.test', $admin, [$bookingA->id]);
        $this->assertBookingIds('/api/admin/bookings?search=other.owner', $admin, [$bookingB->id]);
        $this->assertBookingIds('/api/admin/bookings?search='.$bookingA->id, $admin, [$bookingA->id]);
        $this->assertBookingIds('/api/admin/bookings?status='.Booking::STATUS_PENDING, $admin, [$bookingB->id]);
        $this->assertBookingIds('/api/admin/bookings?property_id='.$riad->id, $admin, [$bookingA->id]);
        $this->assertBookingIds('/api/admin/bookings?guest_id='.$guestA->id, $admin, [$bookingA->id]);
        $this->assertBookingIds('/api/admin/bookings?user_id='.$guestB->id, $admin, [$bookingB->id]);
        $this->assertBookingIds('/api/admin/bookings?owner_id='.$ownerA->id, $admin, [$bookingA->id]);
        $this->assertBookingIds('/api/admin/bookings?city=Casa', $admin, [$bookingB->id]);
        $this->assertBookingIds('/api/admin/bookings?start_date_from=2026-09-01', $admin, [$bookingB->id]);
        $this->assertBookingIds('/api/admin/bookings?end_date_to=2026-08-31', $admin, [$bookingA->id]);
        $this->assertBookingIds('/api/admin/bookings?created_from=2026-07-10', $admin, [$bookingB->id]);
        $this->assertBookingIds('/api/admin/bookings?max_total=500', $admin, [$bookingB->id]);
        $this->assertBookingIds('/api/admin/bookings?min_total=900', $admin, [$bookingA->id]);
        $this->assertBookingIds('/api/admin/bookings?has_review=true', $admin, [$bookingA->id]);
        $this->assertBookingIds('/api/admin/bookings?has_report=true', $admin, [$bookingA->id]);

        $withoutReviews = collect($this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/bookings?has_review=false')
            ->assertOk()
            ->json('data'))->pluck('id');

        $this->assertTrue($withoutReviews->contains($bookingB->id));
        $this->assertFalse($withoutReviews->contains($bookingA->id));
    }

    public function test_sorting_options_work(): void
    {
        Carbon::setTestNow('2026-08-02 12:00:00');
        $admin = $this->admin();
        $owner = User::factory()->create(['role' => 'owner']);
        $guest = User::factory()->create();
        $alpha = $this->propertyFor($owner, ['title' => 'Alpha Property']);
        $zulu = $this->propertyFor($owner, ['title' => 'Zulu Property']);
        $oldest = $this->bookingFor($guest, $zulu, [
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-20',
            'total_price' => 300,
            'status' => Booking::STATUS_PENDING,
            'created_at' => now()->subDays(3),
        ]);
        $middle = $this->bookingFor($guest, $alpha, [
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-12',
            'total_price' => 900,
            'status' => Booking::STATUS_ACCEPTED,
            'created_at' => now()->subDays(2),
        ]);
        $newest = $this->bookingFor($guest, $zulu, [
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-03',
            'total_price' => 100,
            'status' => Booking::STATUS_CANCELLED,
            'created_at' => now(),
        ]);

        $this->assertFirstBooking('/api/admin/bookings?sort=newest', $admin, $newest->id);
        $this->assertFirstBooking('/api/admin/bookings?sort=oldest', $admin, $oldest->id);
        $this->assertFirstBooking('/api/admin/bookings?sort=start_date', $admin, $middle->id);
        $this->assertFirstBooking('/api/admin/bookings?sort=end_date', $admin, $middle->id);
        $this->assertFirstBooking('/api/admin/bookings?sort=total_high', $admin, $middle->id);
        $this->assertFirstBooking('/api/admin/bookings?sort=total_low', $admin, $newest->id);
        $this->assertFirstBooking('/api/admin/bookings?sort=status', $admin, $middle->id);
        $this->assertFirstBooking('/api/admin/bookings?sort=property_title', $admin, $middle->id);
    }

    public function test_booking_resource_privacy(): void
    {
        $admin = $this->admin();
        $owner = User::factory()->create([
            'role' => 'owner',
            'password' => 'secret-password',
            'remember_token' => 'owner-remember-secret',
        ]);
        $guest = User::factory()->create([
            'password' => 'guest-secret-password',
            'remember_token' => 'guest-remember-secret',
        ]);
        $property = $this->propertyFor($owner, ['address' => 'Exact private address']);
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
            ->getJson('/api/admin/bookings/'.$booking->id)
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('password', $json);
        $this->assertStringNotContainsString('remember_token', $json);
        $this->assertStringNotContainsString('owner-remember-secret', $json);
        $this->assertStringNotContainsString('guest-remember-secret', $json);
        $this->assertStringNotContainsString('token', $json);
        $this->assertStringNotContainsString('Exact private address', $json);
        $this->assertStringNotContainsString('Private report body', $json);
        $this->assertStringNotContainsString('Internal admin notes', $json);
        $this->assertStringNotContainsString('Private review comment', $json);
        $this->assertStringNotContainsString('ip_hash', $json);
        $this->assertStringNotContainsString('user_agent_hash', $json);
        $this->assertStringNotContainsString('secret-ip-hash', $json);
        $this->assertStringNotContainsString('secret-agent-hash', $json);
        $this->assertStringNotContainsString('client_secret', $json);
        $this->assertStringNotContainsString('payment_intent', $json);
    }

    public function test_missing_reports_table_is_handled_defensively(): void
    {
        $admin = $this->admin();
        $owner = User::factory()->create(['role' => 'owner']);
        $guest = User::factory()->create();
        $property = $this->propertyFor($owner);
        $booking = $this->bookingFor($guest, $property);

        Schema::dropIfExists('reports_backup_for_admin_bookings_test');
        Schema::rename('reports', 'reports_backup_for_admin_bookings_test');

        try {
            $this
                ->actingAs($admin, 'sanctum')
                ->getJson('/api/admin/bookings/'.$booking->id)
                ->assertOk()
                ->assertJsonPath('data.signals.reports_count', 0)
                ->assertJsonPath('data.reports', []);

            $this
                ->actingAs($admin, 'sanctum')
                ->getJson('/api/admin/bookings?has_report=true')
                ->assertOk()
                ->assertJsonCount(0, 'data');
        } finally {
            Schema::rename('reports_backup_for_admin_bookings_test', 'reports');
        }
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function assertBookingIds(string $url, User $admin, array $expectedIds): void
    {
        $ids = collect($this
            ->actingAs($admin, 'sanctum')
            ->getJson($url)
            ->assertOk()
            ->json('data'))->pluck('id')->all();

        $this->assertSame($expectedIds, $ids);
    }

    private function assertFirstBooking(string $url, User $admin, int $expectedId): void
    {
        $this->assertSame($expectedId, $this
            ->actingAs($admin, 'sanctum')
            ->getJson($url)
            ->assertOk()
            ->json('data.0.id'));
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
        $booking = Booking::create(array_merge([
            'user_id' => $user->id,
            'property_id' => $property->id,
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(7)->toDateString(),
            'total_price' => 500,
            'status' => Booking::STATUS_ACCEPTED,
        ], collect($attributes)->except(['created_at', 'updated_at'])->all()));

        if (array_key_exists('created_at', $attributes) || array_key_exists('updated_at', $attributes)) {
            $booking->forceFill([
                'created_at' => $attributes['created_at'] ?? $booking->created_at,
                'updated_at' => $attributes['updated_at'] ?? $attributes['created_at'] ?? $booking->updated_at,
            ])->save();
        }

        return $booking;
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
