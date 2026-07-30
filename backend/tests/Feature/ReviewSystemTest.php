<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Favorite;
use App\Models\Property;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReviewSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-07-30 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_guest_with_completed_accepted_booking_can_review(): void
    {
        $owner = User::factory()->create();
        $guest = User::factory()->create();
        $property = $this->propertyFor($owner);
        $booking = $this->bookingFor($guest, $property, [
            'status' => 'accepted',
            'end_date' => '2026-07-20',
        ]);

        $this
            ->actingAs($guest, 'sanctum')
            ->postJson('/api/reviews', [
                'property_id' => $property->id,
                'booking_id' => $booking->id,
                'rating' => 5,
                'comment' => 'A flawless stay.',
            ])
            ->assertCreated()
            ->assertJsonPath('message', 'Review submitted.')
            ->assertJsonPath('data.booking_id', $booking->id)
            ->assertJsonPath('data.rating', 5);

        $this->assertDatabaseHas('reviews', [
            'user_id' => $guest->id,
            'property_id' => $property->id,
            'booking_id' => $booking->id,
            'rating' => 5,
        ]);
    }

    public function test_pending_rejected_and_cancelled_bookings_cannot_review(): void
    {
        $owner = User::factory()->create();
        $guest = User::factory()->create();
        $property = $this->propertyFor($owner);

        foreach (['pending', 'rejected', 'cancelled'] as $status) {
            $booking = $this->bookingFor($guest, $property, [
                'status' => $status,
                'end_date' => '2026-07-20',
            ]);

            $this
                ->actingAs($guest, 'sanctum')
                ->postJson('/api/reviews', [
                    'property_id' => $property->id,
                    'booking_id' => $booking->id,
                    'rating' => 5,
                    'comment' => 'Not eligible.',
                ])
                ->assertUnprocessable()
                ->assertJsonPath('message', 'Only completed accepted stays can be reviewed.');
        }
    }

    public function test_future_checkout_cannot_review(): void
    {
        $owner = User::factory()->create();
        $guest = User::factory()->create();
        $property = $this->propertyFor($owner);
        $booking = $this->bookingFor($guest, $property, [
            'status' => 'accepted',
            'end_date' => '2026-08-20',
        ]);

        $this
            ->actingAs($guest, 'sanctum')
            ->postJson('/api/reviews', [
                'property_id' => $property->id,
                'booking_id' => $booking->id,
                'rating' => 5,
                'comment' => 'Too soon.',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Stay is not completed yet.');
    }

    public function test_today_checkout_cannot_review(): void
    {
        $owner = User::factory()->create();
        $guest = User::factory()->create();
        $property = $this->propertyFor($owner);
        $booking = $this->bookingFor($guest, $property, [
            'status' => 'accepted',
            'end_date' => '2026-07-30',
        ]);

        $this
            ->actingAs($guest, 'sanctum')
            ->postJson('/api/reviews', [
                'property_id' => $property->id,
                'booking_id' => $booking->id,
                'rating' => 5,
                'comment' => 'Still checkout day.',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Stay is not completed yet.');
    }

    public function test_owner_cannot_review_own_property(): void
    {
        $owner = User::factory()->create();
        $property = $this->propertyFor($owner);
        $booking = $this->bookingFor($owner, $property, [
            'status' => 'accepted',
            'end_date' => '2026-07-20',
        ]);

        $this
            ->actingAs($owner, 'sanctum')
            ->postJson('/api/reviews', [
                'property_id' => $property->id,
                'booking_id' => $booking->id,
                'rating' => 5,
                'comment' => 'Mine.',
            ])
            ->assertForbidden()
            ->assertJsonPath('message', 'You cannot review your own property.');
    }

    public function test_unrelated_user_cannot_review_with_someone_elses_booking(): void
    {
        $owner = User::factory()->create();
        $guest = User::factory()->create();
        $other = User::factory()->create();
        $property = $this->propertyFor($owner);
        $booking = $this->bookingFor($guest, $property, [
            'status' => 'accepted',
            'end_date' => '2026-07-20',
        ]);

        $this
            ->actingAs($other, 'sanctum')
            ->postJson('/api/reviews', [
                'property_id' => $property->id,
                'booking_id' => $booking->id,
                'rating' => 5,
                'comment' => 'Not mine.',
            ])
            ->assertForbidden()
            ->assertJsonPath('message', 'This booking is not eligible for review.');
    }

    public function test_rating_must_be_between_one_and_five(): void
    {
        $owner = User::factory()->create();
        $guest = User::factory()->create();
        $property = $this->propertyFor($owner);
        $booking = $this->bookingFor($guest, $property, [
            'status' => 'accepted',
            'end_date' => '2026-07-20',
        ]);

        $this
            ->actingAs($guest, 'sanctum')
            ->postJson('/api/reviews', [
                'property_id' => $property->id,
                'booking_id' => $booking->id,
                'rating' => 0,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('rating');

        $this
            ->actingAs($guest, 'sanctum')
            ->postJson('/api/reviews', [
                'property_id' => $property->id,
                'booking_id' => $booking->id,
                'rating' => 6,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('rating');
    }

    public function test_duplicate_review_for_same_booking_is_rejected(): void
    {
        $owner = User::factory()->create();
        $guest = User::factory()->create();
        $property = $this->propertyFor($owner);
        $booking = $this->bookingFor($guest, $property, [
            'status' => 'accepted',
            'end_date' => '2026-07-20',
        ]);

        Review::create([
            'user_id' => $guest->id,
            'property_id' => $property->id,
            'booking_id' => $booking->id,
            'rating' => 5,
        ]);

        $this
            ->actingAs($guest, 'sanctum')
            ->postJson('/api/reviews', [
                'property_id' => $property->id,
                'booking_id' => $booking->id,
                'rating' => 4,
            ])
            ->assertConflict()
            ->assertJsonPath('message', 'Review already submitted for this booking.');
    }

    public function test_property_index_detail_and_favorites_include_rating_aggregates(): void
    {
        $owner = User::factory()->create();
        $guestA = User::factory()->create();
        $guestB = User::factory()->create();
        $property = $this->propertyFor($owner);

        $this->reviewFor($guestA, $property, 5);
        $this->reviewFor($guestB, $property, 4);

        Favorite::create([
            'user_id' => $guestA->id,
            'property_id' => $property->id,
        ]);

        $this
            ->actingAs($guestA, 'sanctum')
            ->getJson('/api/properties')
            ->assertOk()
            ->assertJsonPath('data.0.average_rating', 4.5)
            ->assertJsonPath('data.0.public_rating', 4.1)
            ->assertJsonPath('data.0.reviews_count', 2);

        $this
            ->actingAs($guestA, 'sanctum')
            ->getJson('/api/properties/'.$property->id)
            ->assertOk()
            ->assertJsonPath('average_rating', 4.5)
            ->assertJsonPath('public_rating', 4.1)
            ->assertJsonPath('reviews_count', 2);

        $this
            ->actingAs($guestA, 'sanctum')
            ->getJson('/api/favorites')
            ->assertOk()
            ->assertJsonPath('data.0.property.average_rating', 4.5)
            ->assertJsonPath('data.0.property.public_rating', 4.1)
            ->assertJsonPath('data.0.property.reviews_count', 2);
    }

    public function test_property_with_no_reviews_returns_empty_rating_contract(): void
    {
        $owner = User::factory()->create();
        $guest = User::factory()->create();
        $property = $this->propertyFor($owner);

        $this
            ->actingAs($guest, 'sanctum')
            ->getJson('/api/properties/'.$property->id)
            ->assertOk()
            ->assertJsonPath('average_rating', null)
            ->assertJsonPath('public_rating', null)
            ->assertJsonPath('rating_label', 'New')
            ->assertJsonPath('reviews_count', 0);
    }

    public function test_one_five_star_review_returns_confidence_adjusted_public_rating(): void
    {
        $owner = User::factory()->create();
        $guest = User::factory()->create();
        $property = $this->propertyFor($owner);

        $this->reviewFor($guest, $property, 5);

        $this
            ->actingAs($guest, 'sanctum')
            ->getJson('/api/properties/'.$property->id)
            ->assertOk()
            ->assertJsonPath('average_rating', 5)
            ->assertJsonPath('public_rating', 4.2)
            ->assertJsonPath('reviews_count', 1);
    }

    public function test_two_five_star_reviews_still_return_public_rating_below_five(): void
    {
        $owner = User::factory()->create();
        $firstGuest = User::factory()->create();
        $secondGuest = User::factory()->create();
        $property = $this->propertyFor($owner);

        $this->reviewFor($firstGuest, $property, 5);
        $this->reviewFor($secondGuest, $property, 5);

        $this
            ->actingAs($firstGuest, 'sanctum')
            ->getJson('/api/properties/'.$property->id)
            ->assertOk()
            ->assertJsonPath('average_rating', 5)
            ->assertJsonPath('public_rating', 4.3)
            ->assertJsonPath('reviews_count', 2);
    }

    public function test_many_strong_reviews_move_public_rating_closer_to_raw_average(): void
    {
        $owner = User::factory()->create();
        $property = $this->propertyFor($owner);

        for ($i = 0; $i < 20; $i++) {
            $this->reviewFor(User::factory()->create(), $property, 5);
        }

        $this
            ->actingAs(User::factory()->create(), 'sanctum')
            ->getJson('/api/properties/'.$property->id)
            ->assertOk()
            ->assertJsonPath('average_rating', 5)
            ->assertJsonPath('public_rating', 4.8)
            ->assertJsonPath('reviews_count', 20);
    }

    public function test_mixed_reviews_calculate_correct_public_rating(): void
    {
        $owner = User::factory()->create();
        $property = $this->propertyFor($owner);

        $this->reviewFor(User::factory()->create(), $property, 5);
        $this->reviewFor(User::factory()->create(), $property, 4);
        $this->reviewFor(User::factory()->create(), $property, 2);

        $this
            ->actingAs(User::factory()->create(), 'sanctum')
            ->getJson('/api/properties/'.$property->id)
            ->assertOk()
            ->assertJsonPath('average_rating', 3.7)
            ->assertJsonPath('public_rating', 3.9)
            ->assertJsonPath('reviews_count', 3);
    }

    public function test_rating_sort_uses_public_rating_weighted_score(): void
    {
        $owner = User::factory()->create();
        $onePerfectReview = $this->propertyFor($owner, ['title' => 'One perfect']);
        $manyStrongReviews = $this->propertyFor($owner, ['title' => 'Many strong']);

        $this->reviewFor(User::factory()->create(), $onePerfectReview, 5);

        for ($i = 0; $i < 10; $i++) {
            $this->reviewFor(User::factory()->create(), $manyStrongReviews, 5);
        }

        $this
            ->actingAs(User::factory()->create(), 'sanctum')
            ->getJson('/api/properties?sort=rating')
            ->assertOk()
            ->assertJsonPath('data.0.id', $manyStrongReviews->id)
            ->assertJsonPath('data.0.public_rating', 4.7)
            ->assertJsonPath('data.1.id', $onePerfectReview->id)
            ->assertJsonPath('data.1.public_rating', 4.2);
    }

    public function test_property_detail_returns_only_current_guests_review_eligible_bookings(): void
    {
        $owner = User::factory()->create();
        $guest = User::factory()->create();
        $otherGuest = User::factory()->create();
        $property = $this->propertyFor($owner);
        $eligible = $this->bookingFor($guest, $property, [
            'status' => 'accepted',
            'start_date' => '2026-07-20',
            'end_date' => '2026-07-29',
        ]);
        $today = $this->bookingFor($guest, $property, [
            'status' => 'accepted',
            'start_date' => '2026-07-25',
            'end_date' => '2026-07-30',
        ]);
        $future = $this->bookingFor($guest, $property, [
            'status' => 'accepted',
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-05',
        ]);
        $pending = $this->bookingFor($guest, $property, [
            'status' => 'pending',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-10',
        ]);
        $otherUsersBooking = $this->bookingFor($otherGuest, $property, [
            'status' => 'accepted',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-10',
        ]);

        $response = $this
            ->actingAs($guest, 'sanctum')
            ->getJson('/api/properties/'.$property->id)
            ->assertOk()
            ->assertJsonPath('review_eligible_bookings.0.id', $eligible->id)
            ->assertJsonPath('review_eligible_bookings.0.start_date', '2026-07-20')
            ->assertJsonPath('review_eligible_bookings.0.end_date', '2026-07-29');

        $eligibleIds = collect($response->json('review_eligible_bookings'))->pluck('id')->all();
        $this->assertSame([$eligible->id], $eligibleIds);
        $this->assertNotContains($today->id, $eligibleIds);
        $this->assertNotContains($future->id, $eligibleIds);
        $this->assertNotContains($pending->id, $eligibleIds);
        $this->assertNotContains($otherUsersBooking->id, $eligibleIds);
        $this->assertArrayNotHasKey('check_in', $response->json('review_eligible_bookings.0'));
        $this->assertArrayNotHasKey('check_out', $response->json('review_eligible_bookings.0'));
    }

    public function test_property_detail_does_not_expose_review_eligible_bookings_to_owner_or_unrelated_user(): void
    {
        $owner = User::factory()->create();
        $guest = User::factory()->create();
        $unrelated = User::factory()->create();
        $property = $this->propertyFor($owner);

        $this->bookingFor($guest, $property, [
            'status' => 'accepted',
            'end_date' => '2026-07-29',
        ]);

        $this
            ->actingAs($owner, 'sanctum')
            ->getJson('/api/properties/'.$property->id)
            ->assertOk()
            ->assertJsonPath('review_eligible_bookings', []);

        $this
            ->actingAs($unrelated, 'sanctum')
            ->getJson('/api/properties/'.$property->id)
            ->assertOk()
            ->assertJsonPath('review_eligible_bookings', []);
    }

    public function test_property_pagination_and_filters_still_work_with_rating_aggregates(): void
    {
        $owner = User::factory()->create();
        $guest = User::factory()->create();

        for ($i = 1; $i <= 13; $i++) {
            $this->propertyFor($owner, [
                'title' => 'Filtered '.$i,
                'city' => 'Marrakech',
            ]);
        }

        $this
            ->actingAs($guest, 'sanctum')
            ->getJson('/api/properties?city=Marrakech&page=2&per_page=12')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('data.0.average_rating', null)
            ->assertJsonPath('data.0.public_rating', null)
            ->assertJsonPath('data.0.rating_label', 'New')
            ->assertJsonPath('data.0.reviews_count', 0);
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
            'start_date' => '2026-07-10',
            'end_date' => '2026-07-20',
            'total_price' => 500,
            'status' => 'accepted',
        ], $attributes));
    }

    private function reviewFor(User $user, Property $property, int $rating): Review
    {
        $booking = $this->bookingFor($user, $property);

        return Review::create([
            'user_id' => $user->id,
            'property_id' => $property->id,
            'booking_id' => $booking->id,
            'rating' => $rating,
            'comment' => 'Lovely stay.',
        ]);
    }
}
