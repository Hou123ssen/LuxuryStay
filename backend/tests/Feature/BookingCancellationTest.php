<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Notification;
use App\Models\Property;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingCancellationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-07-31 10:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_guest_can_cancel_own_pending_future_booking(): void
    {
        $owner = User::factory()->create();
        $guest = User::factory()->create(['name' => 'Draga']);
        $property = $this->createPropertyFor($owner, ['title' => 'Review']);
        $booking = $this->createBookingFor($guest, $property, [
            'status' => Booking::STATUS_PENDING,
        ]);

        $response = $this
            ->actingAs($guest, 'sanctum')
            ->postJson('/api/bookings/'.$booking->id.'/cancel', [
                'reason' => 'Travel plans changed.',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Booking cancelled successfully.')
            ->assertJsonPath('booking.status', Booking::STATUS_CANCELLED)
            ->assertJsonPath('booking.cancellation_actor', Booking::CANCELLATION_ACTOR_GUEST)
            ->assertJsonPath('booking.cancellation_reason', 'Travel plans changed.');

        $booking->refresh();
        $this->assertNotNull($booking->cancelled_at);
        $this->assertSame($guest->id, $booking->cancelled_by_user_id);

        $notification = Notification::where('user_id', $owner->id)->firstOrFail();
        $payload = json_decode($notification->message, true);
        $this->assertSame('booking_cancelled_by_guest', $payload['type']);
        $this->assertSame($booking->id, $payload['booking_id']);
        $this->assertSame($property->id, $payload['property_id']);
        $this->assertSame('Review', $payload['property_title']);
        $this->assertSame('Draga', $payload['guest_name']);
        $this->assertSame('guest', $payload['cancelled_by']);
        $this->assertSame('Travel plans changed.', $payload['cancellation_reason']);
        $this->assertNull($notification->read);
    }

    public function test_guest_can_cancel_own_accepted_future_booking(): void
    {
        $owner = User::factory()->create();
        $guest = User::factory()->create();
        $property = $this->createPropertyFor($owner);
        $booking = $this->createBookingFor($guest, $property, [
            'status' => Booking::STATUS_ACCEPTED,
        ]);

        $this
            ->actingAs($guest, 'sanctum')
            ->postJson('/api/bookings/'.$booking->id.'/cancel')
            ->assertOk()
            ->assertJsonPath('booking.status', Booking::STATUS_CANCELLED);
    }

    public function test_guest_cannot_cancel_another_users_booking(): void
    {
        $owner = User::factory()->create();
        $guest = User::factory()->create();
        $otherGuest = User::factory()->create();
        $property = $this->createPropertyFor($owner);
        $booking = $this->createBookingFor($guest, $property);

        $this
            ->actingAs($otherGuest, 'sanctum')
            ->postJson('/api/bookings/'.$booking->id.'/cancel')
            ->assertForbidden();

        $this->assertSame(Booking::STATUS_PENDING, $booking->refresh()->status);
    }

    public function test_owner_can_cancel_accepted_future_booking_for_own_property(): void
    {
        $owner = User::factory()->create(['name' => 'Mouna']);
        $guest = User::factory()->create();
        $property = $this->createPropertyFor($owner, ['title' => 'Riad']);
        $booking = $this->createBookingFor($guest, $property, [
            'status' => Booking::STATUS_ACCEPTED,
        ]);

        $this
            ->actingAs($owner, 'sanctum')
            ->postJson('/api/bookings/'.$booking->id.'/cancel', [
                'reason' => 'Emergency maintenance.',
            ])
            ->assertOk()
            ->assertJsonPath('booking.status', Booking::STATUS_CANCELLED)
            ->assertJsonPath('booking.cancellation_actor', Booking::CANCELLATION_ACTOR_OWNER);

        $notification = Notification::where('user_id', $guest->id)->firstOrFail();
        $payload = json_decode($notification->message, true);
        $this->assertSame('booking_cancelled_by_owner', $payload['type']);
        $this->assertSame('Mouna', $payload['owner_name']);
        $this->assertSame('owner', $payload['cancelled_by']);
        $this->assertSame('Emergency maintenance.', $payload['cancellation_reason']);
    }

    public function test_owner_cannot_cancel_booking_for_property_they_do_not_own(): void
    {
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();
        $guest = User::factory()->create();
        $property = $this->createPropertyFor($owner);
        $booking = $this->createBookingFor($guest, $property, [
            'status' => Booking::STATUS_ACCEPTED,
        ]);

        $this
            ->actingAs($otherOwner, 'sanctum')
            ->postJson('/api/bookings/'.$booking->id.'/cancel')
            ->assertForbidden();
    }

    public function test_owner_cannot_cancel_pending_booking_through_cancel_endpoint(): void
    {
        $owner = User::factory()->create();
        $guest = User::factory()->create();
        $property = $this->createPropertyFor($owner);
        $booking = $this->createBookingFor($guest, $property, [
            'status' => Booking::STATUS_PENDING,
        ]);

        $this
            ->actingAs($owner, 'sanctum')
            ->postJson('/api/bookings/'.$booking->id.'/cancel')
            ->assertStatus(409)
            ->assertJsonPath('message', 'This booking can no longer be cancelled.');

        $this->assertSame(Booking::STATUS_PENDING, $booking->refresh()->status);
    }

    public function test_cannot_cancel_after_stay_start_date(): void
    {
        $owner = User::factory()->create();
        $guest = User::factory()->create();
        $property = $this->createPropertyFor($owner);
        $booking = $this->createBookingFor($guest, $property, [
            'status' => Booking::STATUS_ACCEPTED,
            'start_date' => '2026-07-31',
            'end_date' => '2026-08-02',
        ]);

        $this
            ->actingAs($guest, 'sanctum')
            ->postJson('/api/bookings/'.$booking->id.'/cancel')
            ->assertStatus(409)
            ->assertJsonPath('message', 'This stay has already started and cannot be cancelled.');
    }

    public function test_cannot_cancel_already_cancelled_rejected_or_completed_booking(): void
    {
        $owner = User::factory()->create();
        $guest = User::factory()->create();
        $property = $this->createPropertyFor($owner);

        foreach ([Booking::STATUS_CANCELLED, Booking::STATUS_REJECTED, Booking::STATUS_COMPLETED] as $status) {
            $booking = $this->createBookingFor($guest, $property, [
                'status' => $status,
                'start_date' => '2026-08-05',
                'end_date' => '2026-08-07',
            ]);

            $response = $this
                ->actingAs($guest, 'sanctum')
                ->postJson('/api/bookings/'.$booking->id.'/cancel');

            $response->assertStatus(409);
            $this->assertSame($status, $booking->refresh()->status);
        }
    }

    public function test_cancelled_accepted_booking_no_longer_blocks_availability_or_acceptance(): void
    {
        $owner = User::factory()->create();
        $guestA = User::factory()->create();
        $guestB = User::factory()->create();
        $property = $this->createPropertyFor($owner);
        $cancelled = $this->createBookingFor($guestA, $property, [
            'status' => Booking::STATUS_ACCEPTED,
            'start_date' => '2026-08-05',
            'end_date' => '2026-08-08',
        ]);

        $this
            ->actingAs($guestA, 'sanctum')
            ->postJson('/api/bookings/'.$cancelled->id.'/cancel')
            ->assertOk();

        $this
            ->getJson('/api/properties/'.$property->id.'/availability')
            ->assertOk()
            ->assertJsonPath('unavailable_dates', []);

        $pending = $this->createBookingFor($guestB, $property, [
            'status' => Booking::STATUS_PENDING,
            'start_date' => '2026-08-06',
            'end_date' => '2026-08-09',
        ]);

        $this
            ->actingAs($owner, 'sanctum')
            ->postJson('/api/bookings/'.$pending->id.'/accept')
            ->assertOk()
            ->assertJsonPath('data.status', Booking::STATUS_ACCEPTED);
    }

    public function test_cancelled_booking_does_not_make_review_form_eligible(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 10:00:00'));

        $owner = User::factory()->create();
        $guest = User::factory()->create();
        $property = $this->createPropertyFor($owner);
        $booking = $this->createBookingFor($guest, $property, [
            'status' => Booking::STATUS_CANCELLED,
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-05',
        ]);

        $this
            ->actingAs($guest, 'sanctum')
            ->getJson('/api/properties/'.$property->id)
            ->assertOk()
            ->assertJsonPath('review_eligible_bookings', []);

        $this->assertSame(Booking::STATUS_CANCELLED, $booking->refresh()->status);
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
            'start_date' => '2026-08-05',
            'end_date' => '2026-08-07',
            'total_price' => 500,
            'status' => Booking::STATUS_PENDING,
        ], $attributes));
    }
}
