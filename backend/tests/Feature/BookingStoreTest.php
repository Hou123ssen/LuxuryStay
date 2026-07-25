<?php

namespace Tests\Feature;

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

        $response = $this
            ->actingAs($guest, 'sanctum')
            ->postJson('/api/bookings', [
                'property_id' => $property->id,
                'start_date' => '2026-08-01',
                'end_date' => '2026-08-03',
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

        $this
            ->actingAs($guest, 'sanctum')
            ->postJson('/api/bookings', [
                'property_id' => $property->id,
                'start_date' => '2026-08-01',
                'end_date' => '2026-08-03',
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

        $this
            ->actingAs($guest, 'sanctum')
            ->postJson('/api/bookings', [
                'property_id' => $property->id,
                'start_date' => '2026-08-01',
                'end_date' => '2026-08-03',
            ])
            ->assertCreated();

        $property->update(['price_per_night' => 5000]);

        $this->assertDatabaseHas('bookings', [
            'user_id' => $guest->id,
            'property_id' => $property->id,
            'total_price' => 2000,
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
}
