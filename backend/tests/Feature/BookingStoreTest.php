<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_non_owner_can_create_booking(): void
    {
        $host = User::factory()->create();
        $guest = User::factory()->create();
        $property = $this->createPropertyFor($host, ['price_per_night' => 1000]);
        $startDate = now()->addDay()->toDateString();
        $endDate = now()->addDays(3)->toDateString();

        $response = $this
            ->actingAs($guest, 'sanctum')
            ->postJson('/api/bookings', [
                'property_id' => $property->id,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('message', 'Booking request sent successfully.')
            ->assertJsonPath('data.user_id', $guest->id)
            ->assertJsonPath('data.property_id', $property->id)
            ->assertJsonPath('data.total_price', 2000)
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('bookings', [
            'user_id' => $guest->id,
            'property_id' => $property->id,
            'total_price' => 2000,
            'status' => 'pending',
        ]);
        $this->assertDatabaseCount('notifications', 2);
    }

    public function test_booking_total_price_ignores_client_supplied_value(): void
    {
        $host = User::factory()->create();
        $guest = User::factory()->create();
        $property = $this->createPropertyFor($host, ['price_per_night' => 1000]);
        $startDate = now()->addDay()->toDateString();
        $endDate = now()->addDays(3)->toDateString();

        $this
            ->actingAs($guest, 'sanctum')
            ->postJson('/api/bookings', [
                'property_id' => $property->id,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'total_price' => 1,
            ])
            ->assertCreated()
            ->assertJsonPath('data.total_price', 2000);

        $this->assertDatabaseHas('bookings', [
            'user_id' => $guest->id,
            'property_id' => $property->id,
            'total_price' => 2000,
        ]);
    }

    public function test_booking_keeps_total_price_snapshot_after_property_price_changes(): void
    {
        $host = User::factory()->create();
        $guest = User::factory()->create();
        $property = $this->createPropertyFor($host, ['price_per_night' => 1000]);
        $startDate = now()->addDay()->toDateString();
        $endDate = now()->addDays(3)->toDateString();

        $this
            ->actingAs($guest, 'sanctum')
            ->postJson('/api/bookings', [
                'property_id' => $property->id,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ])
            ->assertCreated();

        $property->update(['price_per_night' => 5000]);

        $this->assertDatabaseHas('bookings', [
            'user_id' => $guest->id,
            'property_id' => $property->id,
            'total_price' => 2000,
        ]);
    }

    public function test_booking_creation_allows_back_to_back_checkout_and_checkin_dates(): void
    {
        $host = User::factory()->create();
        $guestA = User::factory()->create();
        $guestB = User::factory()->create();
        $property = $this->createPropertyFor($host);
        $acceptedStartDate = now()->addDays(5)->toDateString();
        $acceptedEndDate = now()->addDays(7)->toDateString();
        $newEndDate = now()->addDays(12)->toDateString();

        $this->createBookingFor($guestA, $property, [
            'start_date' => $acceptedStartDate,
            'end_date' => $acceptedEndDate,
            'status' => 'accepted',
        ]);

        $this
            ->actingAs($guestB, 'sanctum')
            ->postJson('/api/bookings', [
                'property_id' => $property->id,
                'start_date' => $acceptedEndDate,
                'end_date' => $newEndDate,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('bookings', [
            'user_id' => $guestB->id,
            'property_id' => $property->id,
            'start_date' => $acceptedEndDate,
            'end_date' => $newEndDate,
            'status' => 'pending',
        ]);
    }

    public function test_booking_creation_rejects_real_overlap_with_accepted_booking(): void
    {
        $host = User::factory()->create();
        $guestA = User::factory()->create();
        $guestB = User::factory()->create();
        $property = $this->createPropertyFor($host);
        $acceptedStartDate = now()->addDays(5)->toDateString();
        $acceptedEndDate = now()->addDays(7)->toDateString();
        $overlapStartDate = now()->addDays(6)->toDateString();
        $overlapEndDate = now()->addDays(12)->toDateString();

        $this->createBookingFor($guestA, $property, [
            'start_date' => $acceptedStartDate,
            'end_date' => $acceptedEndDate,
            'status' => 'accepted',
        ]);

        $this
            ->actingAs($guestB, 'sanctum')
            ->postJson('/api/bookings', [
                'property_id' => $property->id,
                'start_date' => $overlapStartDate,
                'end_date' => $overlapEndDate,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Property is already booked for these dates.');

        $this->assertDatabaseMissing('bookings', [
            'user_id' => $guestB->id,
            'property_id' => $property->id,
            'start_date' => $overlapStartDate,
            'end_date' => $overlapEndDate,
        ]);
    }

    public function test_property_owner_cannot_book_own_property(): void
    {
        $owner = User::factory()->create();
        $property = $this->createPropertyFor($owner);

        $response = $this
            ->actingAs($owner, 'sanctum')
            ->postJson('/api/bookings', [
                'property_id' => $property->id,
                'start_date' => now()->addDays(3)->toDateString(),
                'end_date' => now()->addDays(5)->toDateString(),
            ]);

        $response
            ->assertForbidden()
            ->assertJsonPath('message', 'You cannot book your own property.');

        $this->assertDatabaseMissing('bookings', [
            'user_id' => $owner->id,
            'property_id' => $property->id,
        ]);
        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_booking_request_notification_is_created_for_owner_with_safe_metadata(): void
    {
        $host = User::factory()->create();
        $guest = User::factory()->create(['name' => 'Draga']);
        $property = $this->createPropertyFor($host, [
            'title' => 'Mansoria',
            'price_per_night' => 1000,
        ]);
        $startDate = now()->addDay()->toDateString();
        $endDate = now()->addDays(3)->toDateString();

        $response = $this
            ->actingAs($guest, 'sanctum')
            ->postJson('/api/bookings', [
                'property_id' => $property->id,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ])
            ->assertCreated();

        $notification = Notification::where('user_id', $host->id)->firstOrFail();
        $payload = json_decode($notification->message, true);

        $this->assertNull($notification->read);
        $this->assertSame('booking_request', $payload['type']);
        $this->assertSame($response->json('data.id'), $payload['booking_id']);
        $this->assertSame($property->id, $payload['property_id']);
        $this->assertSame('Mansoria', $payload['property_title']);
        $this->assertSame('Draga', $payload['guest_name']);
        $this->assertSame($startDate, $payload['check_in']);
        $this->assertSame($endDate, $payload['check_out']);
        $this->assertArrayNotHasKey('guest_email', $payload);
    }

    public function test_notification_response_includes_booking_request_metadata(): void
    {
        $host = User::factory()->create();
        $guest = User::factory()->create(['name' => 'Draga']);
        $property = $this->createPropertyFor($host, ['title' => 'Mansoria']);
        $startDate = now()->addDay()->toDateString();
        $endDate = now()->addDays(3)->toDateString();

        $bookingResponse = $this
            ->actingAs($guest, 'sanctum')
            ->postJson('/api/bookings', [
                'property_id' => $property->id,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ])
            ->assertCreated();

        $this
            ->actingAs($host, 'sanctum')
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.type', 'booking_request')
            ->assertJsonPath('data.0.booking_id', $bookingResponse->json('data.id'))
            ->assertJsonPath('data.0.property_id', $property->id)
            ->assertJsonPath('data.0.property_title', 'Mansoria')
            ->assertJsonPath('data.0.guest_name', 'Draga')
            ->assertJsonPath('data.0.check_in', $startDate)
            ->assertJsonPath('data.0.check_out', $endDate)
            ->assertJsonPath('data.0.read', false);
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

    private function createBookingFor(User $user, Property $property, array $attributes = [])
    {
        return \App\Models\Booking::create(array_merge([
            'user_id' => $user->id,
            'property_id' => $property->id,
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-03',
            'total_price' => 500,
            'status' => 'pending',
        ], $attributes));
    }
}
