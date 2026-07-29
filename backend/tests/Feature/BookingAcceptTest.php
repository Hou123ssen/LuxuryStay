<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingAcceptTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_accept_pending_booking_without_accepted_overlap(): void
    {
        $host = User::factory()->create();
        $guest = User::factory()->create();
        $property = $this->createPropertyFor($host);
        $booking = $this->createBookingFor($guest, $property, [
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-03',
            'status' => 'pending',
        ]);

        $response = $this
            ->actingAs($host, 'sanctum')
            ->postJson('/api/bookings/'.$booking->id.'/accept');

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Booking accepted.')
            ->assertJsonPath('data.status', 'accepted');

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'accepted',
        ]);
        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_owner_cannot_accept_booking_when_accepted_booking_overlaps_same_property(): void
    {
        $host = User::factory()->create();
        $guestA = User::factory()->create();
        $guestB = User::factory()->create();
        $property = $this->createPropertyFor($host);

        $this->createBookingFor($guestA, $property, [
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-03',
            'status' => 'accepted',
        ]);
        $blockedBooking = $this->createBookingFor($guestB, $property, [
            'start_date' => '2026-08-02',
            'end_date' => '2026-08-04',
            'status' => 'pending',
        ]);

        $response = $this
            ->actingAs($host, 'sanctum')
            ->postJson('/api/bookings/'.$blockedBooking->id.'/accept');

        $response
            ->assertStatus(409)
            ->assertJsonPath('message', 'These dates are no longer available.');

        $this->assertDatabaseHas('bookings', [
            'id' => $blockedBooking->id,
            'status' => 'pending',
        ]);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_accepted_booking_for_different_property_does_not_block_acceptance(): void
    {
        $host = User::factory()->create();
        $guestA = User::factory()->create();
        $guestB = User::factory()->create();
        $otherProperty = $this->createPropertyFor($host, ['title' => 'Other Suite']);
        $property = $this->createPropertyFor($host);

        $this->createBookingFor($guestA, $otherProperty, [
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-03',
            'status' => 'accepted',
        ]);
        $booking = $this->createBookingFor($guestB, $property, [
            'start_date' => '2026-08-02',
            'end_date' => '2026-08-04',
            'status' => 'pending',
        ]);

        $this
            ->actingAs($host, 'sanctum')
            ->postJson('/api/bookings/'.$booking->id.'/accept')
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted');

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'accepted',
        ]);
    }

    public function test_rejected_booking_does_not_block_acceptance(): void
    {
        $host = User::factory()->create();
        $guestA = User::factory()->create();
        $guestB = User::factory()->create();
        $property = $this->createPropertyFor($host);

        $this->createBookingFor($guestA, $property, [
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-03',
            'status' => 'rejected',
        ]);
        $booking = $this->createBookingFor($guestB, $property, [
            'start_date' => '2026-08-02',
            'end_date' => '2026-08-04',
            'status' => 'pending',
        ]);

        $this
            ->actingAs($host, 'sanctum')
            ->postJson('/api/bookings/'.$booking->id.'/accept')
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted');

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'accepted',
        ]);
    }

    public function test_owner_can_reject_pending_booking_and_status_becomes_rejected(): void
    {
        $host = User::factory()->create();
        $guest = User::factory()->create();
        $property = $this->createPropertyFor($host);
        $booking = $this->createBookingFor($guest, $property, [
            'status' => 'pending',
        ]);

        $this
            ->actingAs($host, 'sanctum')
            ->postJson('/api/bookings/'.$booking->id.'/reject')
            ->assertOk()
            ->assertJsonPath('message', 'Booking rejected.')
            ->assertJsonPath('data.status', 'rejected');

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'rejected',
        ]);
    }

    public function test_owner_accepts_pending_booking_and_availability_updates(): void
    {
        $host = User::factory()->create();
        $guest = User::factory()->create();
        $property = $this->createPropertyFor($host);
        $booking = $this->createBookingFor($guest, $property, [
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-03',
            'status' => 'pending',
        ]);

        $this
            ->actingAs($host, 'sanctum')
            ->getJson('/api/properties/'.$property->id.'/availability')
            ->assertOk()
            ->assertJsonPath('unavailable_dates', []);

        $this
            ->actingAs($host, 'sanctum')
            ->postJson('/api/bookings/'.$booking->id.'/accept')
            ->assertOk();

        $this
            ->actingAs($host, 'sanctum')
            ->getJson('/api/properties/'.$property->id.'/availability')
            ->assertOk()
            ->assertJsonPath('unavailable_dates', [
                '2026-08-01',
                '2026-08-02',
            ]);
    }

    public function test_owner_rejects_pending_booking_and_availability_stays_open(): void
    {
        $host = User::factory()->create();
        $guest = User::factory()->create();
        $property = $this->createPropertyFor($host);
        $booking = $this->createBookingFor($guest, $property, [
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-03',
            'status' => 'pending',
        ]);

        $this
            ->actingAs($host, 'sanctum')
            ->postJson('/api/bookings/'.$booking->id.'/reject')
            ->assertOk();

        $this
            ->actingAs($host, 'sanctum')
            ->getJson('/api/properties/'.$property->id.'/availability')
            ->assertOk()
            ->assertJsonPath('unavailable_dates', []);
    }

    public function test_back_to_back_accepted_bookings_are_allowed_on_acceptance(): void
    {
        $host = User::factory()->create();
        $guestA = User::factory()->create();
        $guestB = User::factory()->create();
        $property = $this->createPropertyFor($host);

        $this->createBookingFor($guestA, $property, [
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-03',
            'status' => 'accepted',
        ]);
        $booking = $this->createBookingFor($guestB, $property, [
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-05',
            'status' => 'pending',
        ]);

        $this
            ->actingAs($host, 'sanctum')
            ->postJson('/api/bookings/'.$booking->id.'/accept')
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted');
    }

    public function test_guest_cannot_accept_or_reject_own_booking(): void
    {
        $host = User::factory()->create();
        $guest = User::factory()->create();
        $property = $this->createPropertyFor($host);
        $booking = $this->createBookingFor($guest, $property);

        $this
            ->actingAs($guest, 'sanctum')
            ->postJson('/api/bookings/'.$booking->id.'/accept')
            ->assertForbidden();

        $this
            ->actingAs($guest, 'sanctum')
            ->postJson('/api/bookings/'.$booking->id.'/reject')
            ->assertForbidden();

        $this->assertSame('pending', $booking->refresh()->status);
    }

    public function test_non_owner_cannot_accept_or_reject_booking(): void
    {
        $host = User::factory()->create();
        $guest = User::factory()->create();
        $outsider = User::factory()->create();
        $property = $this->createPropertyFor($host);
        $booking = $this->createBookingFor($guest, $property);

        $this
            ->actingAs($outsider, 'sanctum')
            ->postJson('/api/bookings/'.$booking->id.'/accept')
            ->assertForbidden();

        $this
            ->actingAs($outsider, 'sanctum')
            ->postJson('/api/bookings/'.$booking->id.'/reject')
            ->assertForbidden();

        $this->assertSame('pending', $booking->refresh()->status);
    }

    private function createPropertyFor(User $user, array $attributes = []): Property
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

    private function createBookingFor(User $user, Property $property, array $attributes = []): Booking
    {
        return Booking::create(array_merge([
            'user_id' => $user->id,
            'property_id' => $property->id,
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-03',
            'total_price' => 500,
            'status' => 'pending',
        ], $attributes));
    }
}
