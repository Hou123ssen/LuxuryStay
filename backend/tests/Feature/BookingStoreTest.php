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
        $property = $this->createPropertyFor($host);

        $response = $this
            ->actingAs($guest, 'sanctum')
            ->postJson('/api/bookings', [
                'property_id' => $property->id,
                'start_date' => now()->addDays(3)->toDateString(),
                'end_date' => now()->addDays(5)->toDateString(),
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('message', 'Booking request sent successfully.')
            ->assertJsonPath('data.user_id', $guest->id)
            ->assertJsonPath('data.property_id', $property->id)
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('bookings', [
            'user_id' => $guest->id,
            'property_id' => $property->id,
            'status' => 'pending',
        ]);
        $this->assertDatabaseCount('notifications', 2);
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

    private function createPropertyFor(User $user): Property
    {
        return Property::create([
            'user_id' => $user->id,
            'title' => 'Owner Suite',
            'description' => 'A calm test property.',
            'type' => 'apartment',
            'price_per_night' => 250,
            'city' => 'Marrakech',
            'address' => 'Medina',
        ]);
    }
}
