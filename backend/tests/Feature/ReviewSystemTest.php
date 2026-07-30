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
        config(['reviews.risk.high_risk_threshold' => 80]);
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
            'status' => Review::STATUS_PUBLISHED,
        ]);

        $review = Review::where('booking_id', $booking->id)->first();
        $this->assertNotNull($review->published_at);
        $this->assertIsInt($review->risk_score);
        $this->assertNotSame('127.0.0.1', $review->ip_hash);
        $this->assertNotSame('Symfony', $review->user_agent_hash);
    }

    public function test_high_risk_review_becomes_pending_review_and_stays_private(): void
    {
        config(['reviews.risk.high_risk_threshold' => 70]);

        $owner = User::factory()->create();
        $guestA = User::factory()->create();
        $guestB = User::factory()->create();
        $property = $this->propertyFor($owner);
        $this->reviewFor($guestA, $property, 5, 'Identical polished stay experience.');
        $booking = $this->bookingFor($guestB, $property, [
            'status' => 'accepted',
            'end_date' => '2026-07-20',
        ]);

        $this
            ->actingAs($guestB, 'sanctum')
            ->withServerVariables([
                'REMOTE_ADDR' => '203.0.113.8',
                'HTTP_USER_AGENT' => 'LuxurrStay Test Browser',
            ])
            ->postJson('/api/reviews', [
                'property_id' => $property->id,
                'booking_id' => $booking->id,
                'rating' => 5,
                'comment' => 'Identical polished stay experience.',
            ])
            ->assertCreated()
            ->assertJsonPath('message', 'Your review was received and is being checked before publication.')
            ->assertJsonMissingPath('data.status')
            ->assertJsonMissingPath('data.risk_score')
            ->assertJsonMissingPath('data.risk_reasons')
            ->assertJsonMissingPath('data.ip_hash')
            ->assertJsonMissingPath('data.user_agent_hash');

        $review = Review::where('booking_id', $booking->id)->first();
        $this->assertSame(Review::STATUS_PENDING_REVIEW, $review->status);
        $this->assertNull($review->published_at);
        $this->assertGreaterThanOrEqual(70, $review->risk_score);
        $this->assertContains('DUPLICATE_CONTENT', $review->risk_reasons);
        $this->assertNotSame('203.0.113.8', $review->ip_hash);
        $this->assertNotSame('LuxurrStay Test Browser', $review->user_agent_hash);

        $this
            ->actingAs($guestB, 'sanctum')
            ->getJson('/api/properties/'.$property->id)
            ->assertOk()
            ->assertJsonPath('average_rating', 5)
            ->assertJsonPath('reviews_count', 1)
            ->assertJsonPath('rating_state', 'forming')
            ->assertJsonMissingPath('reviews.1')
            ->assertJsonMissingPath('reviews.0.risk_score')
            ->assertJsonMissingPath('reviews.0.risk_reasons')
            ->assertJsonMissingPath('reviews.0.ip_hash')
            ->assertJsonMissingPath('reviews.0.user_agent_hash');
    }

    public function test_one_star_alone_and_five_star_alone_are_not_fraud(): void
    {
        $owner = User::factory()->create();
        $guestA = User::factory()->create(['created_at' => now()->subDays(10)]);
        $guestB = User::factory()->create(['created_at' => now()->subDays(10)]);
        $firstProperty = $this->propertyFor($owner, ['title' => 'One star fairness']);
        $secondProperty = $this->propertyFor($owner, ['title' => 'Five star fairness']);

        foreach ([[$guestA, $firstProperty, 1], [$guestB, $secondProperty, 5]] as [$guest, $property, $rating]) {
            $booking = $this->bookingFor($guest, $property, [
                'status' => 'accepted',
                'end_date' => '2026-07-20',
            ]);

            $this
                ->actingAs($guest, 'sanctum')
                ->postJson('/api/reviews', [
                    'property_id' => $property->id,
                    'booking_id' => $booking->id,
                    'rating' => $rating,
                    'comment' => 'A distinct verified stay comment.',
                ])
                ->assertCreated()
                ->assertJsonPath('message', 'Review submitted.');

            $review = Review::where('booking_id', $booking->id)->first();
            $this->assertSame(Review::STATUS_PUBLISHED, $review->status);
        }
    }

    public function test_shared_ip_alone_does_not_hold_review_for_moderation(): void
    {
        $owner = User::factory()->create();
        $property = $this->propertyFor($owner);

        foreach ([
            'Quiet arrival and a clean terrace.',
            'Helpful host with an easy checkout.',
            'Comfortable rooms near evening restaurants.',
        ] as $comment) {
            $guest = User::factory()->create(['created_at' => now()->subDays(10)]);
            $booking = $this->bookingFor($guest, $property, [
                'status' => 'accepted',
                'end_date' => '2026-07-20',
            ]);

            $this
                ->actingAs($guest, 'sanctum')
                ->withServerVariables(['REMOTE_ADDR' => '198.51.100.24'])
                ->postJson('/api/reviews', [
                    'property_id' => $property->id,
                    'booking_id' => $booking->id,
                    'rating' => 4,
                    'comment' => $comment,
                ])
                ->assertCreated()
                ->assertJsonPath('message', 'Review submitted.');
        }

        $reviews = Review::where('property_id', $property->id)->latest('id')->get();
        $this->assertSame(3, $reviews->count());
        $this->assertTrue($reviews->every(fn (Review $review) => $review->status === Review::STATUS_PUBLISHED));
        $this->assertContains('SHARED_NETWORK_CLUSTER', $reviews->first()->risk_reasons);
    }

    public function test_burst_pattern_increases_internal_risk(): void
    {
        $owner = User::factory()->create();
        $property = $this->propertyFor($owner);

        foreach ([1, 2, 3, 4] as $number) {
            $guest = User::factory()->create();
            $booking = $this->bookingFor($guest, $property, [
                'status' => 'accepted',
                'end_date' => '2026-07-20',
            ]);

            $this
                ->actingAs($guest, 'sanctum')
                ->postJson('/api/reviews', [
                    'property_id' => $property->id,
                    'booking_id' => $booking->id,
                    'rating' => 4,
                    'comment' => 'Burst pattern comment '.$number,
                ])
                ->assertCreated();
        }

        $review = Review::where('property_id', $property->id)->latest('id')->first();
        $this->assertContains('REVIEW_BURST', $review->risk_reasons);
        $this->assertGreaterThan(0, $review->risk_score);
    }

    public function test_frontend_supplied_moderation_fields_are_ignored_on_review_creation(): void
    {
        $owner = User::factory()->create();
        $guest = User::factory()->create();
        $moderator = User::factory()->create();
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
                'comment' => 'Trying to smuggle moderation fields.',
                'status' => Review::STATUS_REJECTED,
                'published_at' => null,
                'moderated_at' => now()->toDateTimeString(),
                'moderated_by' => $moderator->id,
            ])
            ->assertCreated()
            ->assertJsonMissingPath('data.status')
            ->assertJsonMissingPath('data.published_at')
            ->assertJsonMissingPath('data.moderated_at')
            ->assertJsonMissingPath('data.moderated_by');

        $review = Review::where('booking_id', $booking->id)->first();
        $this->assertSame(Review::STATUS_PUBLISHED, $review->status);
        $this->assertNotNull($review->published_at);
        $this->assertNull($review->moderated_at);
        $this->assertNull($review->moderated_by);
    }

    public function test_unauthenticated_user_cannot_create_review(): void
    {
        $owner = User::factory()->create();
        $guest = User::factory()->create();
        $property = $this->propertyFor($owner);
        $booking = $this->bookingFor($guest, $property, [
            'status' => 'accepted',
            'end_date' => '2026-07-20',
        ]);

        $this
            ->postJson('/api/reviews', [
                'property_id' => $property->id,
                'booking_id' => $booking->id,
                'rating' => 5,
                'comment' => 'Not signed in.',
            ])
            ->assertUnauthorized();
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
            ->assertJsonPath('message', 'This stay has already been reviewed.');

        $this->assertSame(1, Review::where('booking_id', $booking->id)->count());
    }

    public function test_near_simultaneous_duplicate_review_attempts_create_only_one_review(): void
    {
        $owner = User::factory()->create();
        $guest = User::factory()->create();
        $property = $this->propertyFor($owner);
        $booking = $this->bookingFor($guest, $property, [
            'status' => 'accepted',
            'end_date' => '2026-07-20',
        ]);

        $payload = [
            'property_id' => $property->id,
            'booking_id' => $booking->id,
            'rating' => 5,
            'comment' => 'One and only review.',
        ];

        $this
            ->actingAs($guest, 'sanctum')
            ->postJson('/api/reviews', $payload)
            ->assertCreated();

        $this
            ->actingAs($guest, 'sanctum')
            ->postJson('/api/reviews', $payload)
            ->assertConflict()
            ->assertJsonPath('message', 'This stay has already been reviewed.');

        $this->assertSame(1, Review::where('booking_id', $booking->id)->count());
    }

    public function test_review_creation_is_throttled_after_five_attempts_per_hour(): void
    {
        $owner = User::factory()->create();
        $guest = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $property = $this->propertyFor($owner, ['title' => 'Throttle '.$i]);
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
                    'comment' => 'Allowed attempt '.$i,
                ])
                ->assertCreated();
        }

        $blockedProperty = $this->propertyFor($owner, ['title' => 'Throttle blocked']);
        $blockedBooking = $this->bookingFor($guest, $blockedProperty, [
            'status' => 'accepted',
            'end_date' => '2026-07-20',
        ]);

        $this
            ->actingAs($guest, 'sanctum')
            ->postJson('/api/reviews', [
                'property_id' => $blockedProperty->id,
                'booking_id' => $blockedBooking->id,
                'rating' => 5,
                'comment' => 'Too many attempts.',
            ])
            ->assertTooManyRequests();

        $this->assertSame(0, Review::where('booking_id', $blockedBooking->id)->count());
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
            ->assertJsonPath('data.0.rating_state', 'forming')
            ->assertJsonPath('data.0.ranking_score', 4.1)
            ->assertJsonPath('data.0.public_rating', 4.1)
            ->assertJsonPath('data.0.rating_label', 'Rating forming · 2 verified stays')
            ->assertJsonPath('data.0.reviews_count', 2);

        $this
            ->actingAs($guestA, 'sanctum')
            ->getJson('/api/properties/'.$property->id)
            ->assertOk()
            ->assertJsonPath('average_rating', 4.5)
            ->assertJsonPath('rating_state', 'forming')
            ->assertJsonPath('ranking_score', 4.1)
            ->assertJsonPath('public_rating', 4.1)
            ->assertJsonPath('rating_label', 'Rating forming · 2 verified stays')
            ->assertJsonPath('reviews_count', 2);

        $this
            ->actingAs($guestA, 'sanctum')
            ->getJson('/api/favorites')
            ->assertOk()
            ->assertJsonPath('data.0.property.average_rating', 4.5)
            ->assertJsonPath('data.0.property.rating_state', 'forming')
            ->assertJsonPath('data.0.property.ranking_score', 4.1)
            ->assertJsonPath('data.0.property.public_rating', 4.1)
            ->assertJsonPath('data.0.property.rating_label', 'Rating forming · 2 verified stays')
            ->assertJsonPath('data.0.property.reviews_count', 2);
    }

    public function test_pending_and_rejected_reviews_do_not_affect_public_rating_payloads_or_review_list(): void
    {
        $owner = User::factory()->create();
        $guestA = User::factory()->create();
        $guestB = User::factory()->create();
        $guestC = User::factory()->create();
        $property = $this->propertyFor($owner);

        $published = $this->reviewFor($guestA, $property, 5);
        $pending = $this->moderatedReviewFor($guestB, $property, 1, Review::STATUS_PENDING_REVIEW);
        $rejected = $this->moderatedReviewFor($guestC, $property, 1, Review::STATUS_REJECTED);

        Favorite::create([
            'user_id' => $guestA->id,
            'property_id' => $property->id,
        ]);

        $this
            ->actingAs($guestA, 'sanctum')
            ->getJson('/api/properties')
            ->assertOk()
            ->assertJsonPath('data.0.average_rating', 5)
            ->assertJsonPath('data.0.reviews_count', 1)
            ->assertJsonPath('data.0.rating_state', 'forming')
            ->assertJsonPath('data.0.ranking_score', 4.2);

        $detail = $this
            ->actingAs($guestA, 'sanctum')
            ->getJson('/api/properties/'.$property->id)
            ->assertOk()
            ->assertJsonPath('average_rating', 5)
            ->assertJsonPath('reviews_count', 1)
            ->assertJsonPath('rating_state', 'forming')
            ->assertJsonPath('ranking_score', 4.2);

        $reviewIds = collect($detail->json('reviews'))->pluck('id')->all();
        $this->assertSame([$published->id], $reviewIds);
        $this->assertNotContains($pending->id, $reviewIds);
        $this->assertNotContains($rejected->id, $reviewIds);

        $this
            ->actingAs($guestA, 'sanctum')
            ->getJson('/api/favorites')
            ->assertOk()
            ->assertJsonPath('data.0.property.average_rating', 5)
            ->assertJsonPath('data.0.property.reviews_count', 1)
            ->assertJsonPath('data.0.property.rating_state', 'forming')
            ->assertJsonPath('data.0.property.ranking_score', 4.2);
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
            ->assertJsonPath('rating_state', 'new')
            ->assertJsonPath('ranking_score', null)
            ->assertJsonPath('public_rating', null)
            ->assertJsonPath('rating_label', 'New')
            ->assertJsonPath('reviews_count', 0);
    }

    public function test_one_five_star_review_returns_public_average_and_internal_ranking_score(): void
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
            ->assertJsonPath('rating_state', 'forming')
            ->assertJsonPath('ranking_score', 4.2)
            ->assertJsonPath('public_rating', 4.2)
            ->assertJsonPath('rating_label', 'Rating forming · 1 verified stay')
            ->assertJsonPath('reviews_count', 1);
    }

    public function test_two_five_star_reviews_keep_internal_ranking_score_below_five(): void
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
            ->assertJsonPath('rating_state', 'forming')
            ->assertJsonPath('ranking_score', 4.3)
            ->assertJsonPath('public_rating', 4.3)
            ->assertJsonPath('rating_label', 'Rating forming · 2 verified stays')
            ->assertJsonPath('reviews_count', 2);
    }

    public function test_four_reviews_still_return_rating_state_forming(): void
    {
        $owner = User::factory()->create();
        $property = $this->propertyFor($owner);

        for ($i = 0; $i < 4; $i++) {
            $this->reviewFor(User::factory()->create(), $property, 5);
        }

        $this
            ->actingAs(User::factory()->create(), 'sanctum')
            ->getJson('/api/properties/'.$property->id)
            ->assertOk()
            ->assertJsonPath('average_rating', 5)
            ->assertJsonPath('rating_state', 'forming')
            ->assertJsonPath('ranking_score', 4.4)
            ->assertJsonPath('rating_label', 'Rating forming · 4 verified stays')
            ->assertJsonPath('reviews_count', 4);
    }

    public function test_five_reviews_return_rating_state_established(): void
    {
        $owner = User::factory()->create();
        $property = $this->propertyFor($owner);

        foreach ([5, 5, 4, 4, 3] as $rating) {
            $this->reviewFor(User::factory()->create(), $property, $rating);
        }

        $this
            ->actingAs(User::factory()->create(), 'sanctum')
            ->getJson('/api/properties/'.$property->id)
            ->assertOk()
            ->assertJsonPath('average_rating', 4.2)
            ->assertJsonPath('rating_state', 'established')
            ->assertJsonPath('ranking_score', 4.1)
            ->assertJsonPath('rating_label', null)
            ->assertJsonPath('reviews_count', 5);
    }

    public function test_four_published_reviews_and_one_pending_review_stay_forming(): void
    {
        $owner = User::factory()->create();
        $property = $this->propertyFor($owner);

        for ($i = 0; $i < 4; $i++) {
            $this->reviewFor(User::factory()->create(), $property, 5);
        }

        $this->moderatedReviewFor(User::factory()->create(), $property, 5, Review::STATUS_PENDING_REVIEW);

        $this
            ->actingAs(User::factory()->create(), 'sanctum')
            ->getJson('/api/properties/'.$property->id)
            ->assertOk()
            ->assertJsonPath('average_rating', 5)
            ->assertJsonPath('reviews_count', 4)
            ->assertJsonPath('rating_state', 'forming')
            ->assertJsonPath('rating_label', 'Rating forming · 4 verified stays');
    }

    public function test_many_strong_reviews_move_ranking_score_closer_to_raw_average(): void
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
            ->assertJsonPath('rating_state', 'established')
            ->assertJsonPath('ranking_score', 4.8)
            ->assertJsonPath('public_rating', 4.8)
            ->assertJsonPath('rating_label', null)
            ->assertJsonPath('reviews_count', 20);
    }

    public function test_mixed_reviews_calculate_correct_ranking_score(): void
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
            ->assertJsonPath('rating_state', 'forming')
            ->assertJsonPath('ranking_score', 3.9)
            ->assertJsonPath('public_rating', 3.9)
            ->assertJsonPath('rating_label', 'Rating forming · 3 verified stays')
            ->assertJsonPath('reviews_count', 3);
    }

    public function test_rating_sort_uses_internal_ranking_score(): void
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
            ->assertJsonPath('data.0.average_rating', 5)
            ->assertJsonPath('data.0.rating_state', 'established')
            ->assertJsonPath('data.0.ranking_score', 4.7)
            ->assertJsonPath('data.0.public_rating', 4.7)
            ->assertJsonPath('data.1.id', $onePerfectReview->id)
            ->assertJsonPath('data.1.average_rating', 5)
            ->assertJsonPath('data.1.rating_state', 'forming')
            ->assertJsonPath('data.1.ranking_score', 4.2)
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
            ->assertJsonPath('data.0.rating_state', 'new')
            ->assertJsonPath('data.0.ranking_score', null)
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

    private function reviewFor(User $user, Property $property, int $rating, string $comment = 'Lovely stay.'): Review
    {
        $booking = $this->bookingFor($user, $property);

        return Review::create([
            'user_id' => $user->id,
            'property_id' => $property->id,
            'booking_id' => $booking->id,
            'rating' => $rating,
            'comment' => $comment,
        ]);
    }

    private function moderatedReviewFor(User $user, Property $property, int $rating, string $status): Review
    {
        $booking = $this->bookingFor($user, $property);
        $review = new Review([
            'user_id' => $user->id,
            'property_id' => $property->id,
            'booking_id' => $booking->id,
            'rating' => $rating,
            'comment' => 'Hidden from public aggregate.',
        ]);

        $review->status = $status;
        $review->published_at = $status === Review::STATUS_PUBLISHED ? now() : null;
        $review->save();

        return $review;
    }
}
