<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Favorite;
use App\Models\Property;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerReliabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_owner_with_no_bookings_returns_new_null_rate(): void
    {
        $owner = User::factory()->create();
        $property = $this->propertyFor($owner);

        $this
            ->actingAs(User::factory()->create(), 'sanctum')
            ->getJson('/api/properties/'.$property->id)
            ->assertOk()
            ->assertJsonPath('owner_reliability.owner_accepted_bookings_count', 0)
            ->assertJsonPath('owner_reliability.owner_cancelled_bookings_count', 0)
            ->assertJsonPath('owner_reliability.owner_cancellation_rate', null)
            ->assertJsonPath('owner_reliability.owner_reliability_state', 'new')
            ->assertJsonPath('owner_reliability.owner_reliability_label', 'Reliability data not enough yet');
    }

    public function test_accepted_bookings_with_no_owner_cancellations_return_zero_rate(): void
    {
        $owner = User::factory()->create();
        $property = $this->propertyFor($owner);
        $this->bookingsForOwner($owner, 5, Booking::STATUS_ACCEPTED);

        $this
            ->actingAs(User::factory()->create(), 'sanctum')
            ->getJson('/api/properties/'.$property->id)
            ->assertOk()
            ->assertJsonPath('owner_reliability.owner_accepted_bookings_count', 5)
            ->assertJsonPath('owner_reliability.owner_cancelled_bookings_count', 0)
            ->assertJsonPath('owner_reliability.owner_cancellation_rate', 0)
            ->assertJsonPath('owner_reliability.owner_reliability_state', 'forming')
            ->assertJsonPath('owner_reliability.owner_reliability_label', 'Reliability forming');
    }

    public function test_owner_cancellation_counts_but_guest_cancellation_does_not(): void
    {
        $owner = User::factory()->create();
        $property = $this->propertyFor($owner);
        $guest = User::factory()->create();

        $this->bookingFor($guest, $property, [
            'status' => Booking::STATUS_ACCEPTED,
        ]);
        $this->bookingFor($guest, $property, [
            'status' => Booking::STATUS_CANCELLED,
            'cancellation_actor' => Booking::CANCELLATION_ACTOR_OWNER,
            'cancelled_at' => now(),
            'cancelled_by_user_id' => $owner->id,
        ]);
        $this->bookingFor($guest, $property, [
            'status' => Booking::STATUS_CANCELLED,
            'cancellation_actor' => Booking::CANCELLATION_ACTOR_GUEST,
            'cancelled_at' => now(),
            'cancelled_by_user_id' => $guest->id,
        ]);

        $this
            ->actingAs(User::factory()->create(), 'sanctum')
            ->getJson('/api/properties/'.$property->id)
            ->assertOk()
            ->assertJsonPath('owner_reliability.owner_accepted_bookings_count', 2)
            ->assertJsonPath('owner_reliability.owner_cancelled_bookings_count', 1)
            ->assertJsonPath('owner_reliability.owner_cancellation_rate', 50);
    }

    public function test_reliability_state_boundaries_and_labels(): void
    {
        $newOwner = User::factory()->create();
        $newProperty = $this->propertyFor($newOwner, ['title' => 'New']);
        $this->bookingsForOwner($newOwner, 4, Booking::STATUS_ACCEPTED);

        $formingOwner = User::factory()->create();
        $formingProperty = $this->propertyFor($formingOwner, ['title' => 'Forming']);
        $this->bookingsForOwner($formingOwner, 19, Booking::STATUS_ACCEPTED);

        $reliableOwner = User::factory()->create();
        $reliableProperty = $this->propertyFor($reliableOwner, ['title' => 'Reliable']);
        $this->bookingsForOwner($reliableOwner, 20, Booking::STATUS_ACCEPTED);

        $moderateOwner = User::factory()->create();
        $moderateProperty = $this->propertyFor($moderateOwner, ['title' => 'Moderate']);
        $this->bookingsForOwner($moderateOwner, 18, Booking::STATUS_ACCEPTED);
        $this->ownerCancelledBookingsForOwner($moderateOwner, 2);

        $highOwner = User::factory()->create();
        $highProperty = $this->propertyFor($highOwner, ['title' => 'High']);
        $this->bookingsForOwner($highOwner, 16, Booking::STATUS_ACCEPTED);
        $this->ownerCancelledBookingsForOwner($highOwner, 4);

        $user = User::factory()->create();

        $this->assertReliability($user, $newProperty, 'new', 'Reliability data not enough yet', 4, 0, 0);
        $this->assertReliability($user, $formingProperty, 'forming', 'Reliability forming', 19, 0, 0);
        $this->assertReliability($user, $reliableProperty, 'established', 'Reliable host history', 20, 0, 0);
        $this->assertReliability($user, $moderateProperty, 'established', 'Moderate cancellation history', 20, 2, 10);
        $this->assertReliability($user, $highProperty, 'established', 'High cancellation history', 20, 4, 20);
    }

    public function test_property_index_detail_and_favorites_include_owner_reliability(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $property = $this->propertyFor($owner, ['title' => 'Reliability Suite']);
        $this->bookingsForOwner($owner, 6, Booking::STATUS_ACCEPTED);

        Favorite::create([
            'user_id' => $viewer->id,
            'property_id' => $property->id,
        ]);

        $this
            ->actingAs($viewer, 'sanctum')
            ->getJson('/api/properties')
            ->assertOk()
            ->assertJsonPath('data.0.owner_reliability.owner_accepted_bookings_count', 6)
            ->assertJsonPath('data.0.owner_reliability.owner_reliability_state', 'forming');

        $this
            ->actingAs($viewer, 'sanctum')
            ->getJson('/api/properties/'.$property->id)
            ->assertOk()
            ->assertJsonPath('owner_reliability.owner_accepted_bookings_count', 6)
            ->assertJsonPath('owner_reliability.owner_reliability_state', 'forming');

        $this
            ->actingAs($viewer, 'sanctum')
            ->getJson('/api/favorites')
            ->assertOk()
            ->assertJsonPath('data.0.property.owner_reliability.owner_accepted_bookings_count', 6)
            ->assertJsonPath('data.0.property.owner_reliability.owner_reliability_state', 'forming');
    }

    public function test_trust_badge_rules_remain_unchanged_by_owner_reliability(): void
    {
        $owner = User::factory()->create();
        $property = $this->propertyFor($owner);
        $this->publishedReviewsFor($property, 10, 5);
        $this->bookingsForOwner($owner, 6, Booking::STATUS_ACCEPTED);
        $this->ownerCancelledBookingsForOwner($owner, 4);

        $this
            ->actingAs(User::factory()->create(), 'sanctum')
            ->getJson('/api/properties/'.$property->id)
            ->assertOk()
            ->assertJsonPath('trust_badge', 'trusted')
            ->assertJsonPath('trust_label', 'Trusted')
            ->assertJsonPath('owner_reliability.owner_reliability_label', 'High cancellation history');
    }

    private function assertReliability(
        User $viewer,
        Property $property,
        string $state,
        string $label,
        int $acceptedCount,
        int $cancelledCount,
        int|float|null $rate,
    ): void {
        $this
            ->actingAs($viewer, 'sanctum')
            ->getJson('/api/properties/'.$property->id)
            ->assertOk()
            ->assertJsonPath('owner_reliability.owner_accepted_bookings_count', $acceptedCount)
            ->assertJsonPath('owner_reliability.owner_cancelled_bookings_count', $cancelledCount)
            ->assertJsonPath('owner_reliability.owner_cancellation_rate', $rate)
            ->assertJsonPath('owner_reliability.owner_reliability_state', $state)
            ->assertJsonPath('owner_reliability.owner_reliability_label', $label);
    }

    private function propertyFor(User $owner, array $attributes = []): Property
    {
        return Property::create(array_merge([
            'user_id' => $owner->id,
            'title' => 'Owner Suite',
            'description' => 'A calm test property.',
            'type' => 'apartment',
            'price_per_night' => 250,
            'city' => 'Marrakech',
            'address' => 'Medina',
        ], $attributes));
    }

    private function bookingFor(User $guest, Property $property, array $attributes = []): Booking
    {
        return Booking::create(array_merge([
            'user_id' => $guest->id,
            'property_id' => $property->id,
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-03',
            'total_price' => 500,
            'status' => Booking::STATUS_ACCEPTED,
        ], $attributes));
    }

    private function bookingsForOwner(User $owner, int $count, string $status): void
    {
        $property = Property::where('user_id', $owner->id)->first() ?: $this->propertyFor($owner);

        for ($i = 0; $i < $count; $i++) {
            $this->bookingFor(User::factory()->create(), $property, [
                'status' => $status,
                'start_date' => now()->addDays($i + 10)->toDateString(),
                'end_date' => now()->addDays($i + 12)->toDateString(),
            ]);
        }
    }

    private function ownerCancelledBookingsForOwner(User $owner, int $count): void
    {
        $property = Property::where('user_id', $owner->id)->first() ?: $this->propertyFor($owner);

        for ($i = 0; $i < $count; $i++) {
            $this->bookingFor(User::factory()->create(), $property, [
                'status' => Booking::STATUS_CANCELLED,
                'cancellation_actor' => Booking::CANCELLATION_ACTOR_OWNER,
                'cancelled_at' => now(),
                'cancelled_by_user_id' => $owner->id,
                'start_date' => now()->addDays($i + 40)->toDateString(),
                'end_date' => now()->addDays($i + 42)->toDateString(),
            ]);
        }
    }

    private function publishedReviewsFor(Property $property, int $count, int $rating): void
    {
        for ($i = 0; $i < $count; $i++) {
            $guest = User::factory()->create();
            $booking = $this->bookingFor($guest, $property, [
                'status' => Booking::STATUS_ACCEPTED,
                'end_date' => now()->subDays($i + 1)->toDateString(),
            ]);

            Review::create([
                'user_id' => $guest->id,
                'property_id' => $property->id,
                'booking_id' => $booking->id,
                'rating' => $rating,
                'comment' => 'A verified stay.',
            ]);
        }
    }
}
