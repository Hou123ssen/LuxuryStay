<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Notification;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_properties_index_returns_standard_paginated_response(): void
    {
        $user = User::factory()->create();

        for ($i = 1; $i <= 13; $i++) {
            $this->propertyFor($user, ['title' => 'Suite '.$i]);
        }

        $this
            ->actingAs($user, 'sanctum')
            ->getJson('/api/properties?page=2&per_page=12')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.per_page', 12)
            ->assertJsonPath('meta.total', 13)
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'last_page', 'per_page', 'total', 'from', 'to'],
                'links' => ['first', 'last', 'prev', 'next'],
            ]);
    }

    public function test_notifications_index_is_paginated_and_scoped_to_authenticated_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        for ($i = 1; $i <= 11; $i++) {
            Notification::create([
                'user_id' => $user->id,
                'message' => 'Notification '.$i,
            ]);
        }

        Notification::create([
            'user_id' => $other->id,
            'message' => 'Other notification',
        ]);

        $this
            ->actingAs($user, 'sanctum')
            ->getJson('/api/notifications?page=2&per_page=10')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.total', 11);
    }

    public function test_guest_bookings_index_filters_by_tab_and_paginates(): void
    {
        $owner = User::factory()->create();
        $guest = User::factory()->create();
        $otherGuest = User::factory()->create();
        $property = $this->propertyFor($owner);

        for ($i = 1; $i <= 7; $i++) {
            $this->bookingFor($guest, $property, [
                'start_date' => '2026-08-0'.$i,
                'end_date' => '2026-08-1'.$i,
            ]);
        }

        $this->bookingFor($guest, $property, [
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-03',
        ]);
        $this->bookingFor($otherGuest, $property, [
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-03',
        ]);

        $this
            ->actingAs($guest, 'sanctum')
            ->getJson('/api/bookings?tab=upcoming&page=2&per_page=6')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.total', 7);
    }

    public function test_owner_bookings_are_paginated_and_scoped_to_owned_properties(): void
    {
        $owner = User::factory()->create();
        $guest = User::factory()->create();
        $otherOwner = User::factory()->create();
        $ownedProperty = $this->propertyFor($owner);
        $otherProperty = $this->propertyFor($otherOwner);

        for ($i = 1; $i <= 7; $i++) {
            $this->bookingFor($guest, $ownedProperty, [
                'start_date' => '2026-08-0'.$i,
                'end_date' => '2026-08-1'.$i,
            ]);
        }

        $this->bookingFor($guest, $otherProperty);

        $this
            ->actingAs($owner, 'sanctum')
            ->getJson('/api/owner/bookings?page=2&per_page=6')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.total', 7);
    }

    public function test_pagination_per_page_is_capped_at_fifty(): void
    {
        $user = User::factory()->create();

        for ($i = 1; $i <= 55; $i++) {
            $this->propertyFor($user, ['title' => 'Suite '.$i]);
        }

        $this
            ->actingAs($user, 'sanctum')
            ->getJson('/api/properties?per_page=999')
            ->assertOk()
            ->assertJsonCount(50, 'data')
            ->assertJsonPath('meta.per_page', 50);
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
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-03',
            'total_price' => 500,
            'status' => 'pending',
        ], $attributes));
    }
}
