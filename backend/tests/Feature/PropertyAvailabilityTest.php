<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropertyAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_availability_returns_accepted_booking_ranges_and_dates(): void
    {
        $user = User::factory()->create();
        $property = $this->createPropertyFor($user);

        $this->createBookingFor($user, $property, [
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-03',
            'status' => 'accepted',
        ]);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson('/api/properties/'.$property->id.'/availability');

        $response
            ->assertOk()
            ->assertJsonPath('property_id', $property->id)
            ->assertJsonPath('unavailable_ranges.0.start_date', '2026-08-01')
            ->assertJsonPath('unavailable_ranges.0.end_date', '2026-08-03')
            ->assertJsonPath('unavailable_dates', [
                '2026-08-01',
                '2026-08-02',
            ]);
    }

    public function test_availability_ignores_non_accepted_and_other_property_bookings(): void
    {
        $user = User::factory()->create();
        $property = $this->createPropertyFor($user);
        $otherProperty = $this->createPropertyFor($user, ['title' => 'Other Suite']);

        $this->createBookingFor($user, $property, [
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-03',
            'status' => 'pending',
        ]);
        $this->createBookingFor($user, $property, [
            'start_date' => '2026-08-04',
            'end_date' => '2026-08-06',
            'status' => 'rejected',
        ]);
        $this->createBookingFor($user, $property, [
            'start_date' => '2026-08-06',
            'end_date' => '2026-08-08',
            'status' => 'cancelled',
        ]);
        $this->createBookingFor($user, $property, [
            'start_date' => '2026-08-08',
            'end_date' => '2026-08-10',
            'status' => 'declined',
        ]);
        $this->createBookingFor($user, $property, [
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-12',
            'status' => 'missed',
        ]);
        $this->createBookingFor($user, $otherProperty, [
            'start_date' => '2026-08-07',
            'end_date' => '2026-08-09',
            'status' => 'accepted',
        ]);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson('/api/properties/'.$property->id.'/availability');

        $response
            ->assertOk()
            ->assertJsonPath('property_id', $property->id)
            ->assertJsonPath('unavailable_ranges', [])
            ->assertJsonPath('unavailable_dates', []);
    }

    public function test_missing_property_availability_returns_not_found(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user, 'sanctum')
            ->getJson('/api/properties/999999/availability')
            ->assertNotFound();
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
