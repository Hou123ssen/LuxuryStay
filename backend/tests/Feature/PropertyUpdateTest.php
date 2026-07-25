<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropertyUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_property_owner_can_update_allowed_fields(): void
    {
        $owner = User::factory()->create();
        $property = $this->createPropertyFor($owner);

        $response = $this
            ->actingAs($owner, 'sanctum')
            ->putJson('/api/properties/'.$property->id, [
                'title' => 'Updated Suite',
                'price_per_night' => 375,
                'city' => 'Casablanca',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated Suite')
            ->assertJsonPath('data.city', 'Casablanca');

        $this->assertDatabaseHas('properties', [
            'id' => $property->id,
            'user_id' => $owner->id,
            'title' => 'Updated Suite',
            'price_per_night' => 375,
            'city' => 'Casablanca',
        ]);
    }

    public function test_non_owner_cannot_update_another_users_property(): void
    {
        $owner = User::factory()->create();
        $nonOwner = User::factory()->create();
        $property = $this->createPropertyFor($owner);

        $response = $this
            ->actingAs($nonOwner, 'sanctum')
            ->putJson('/api/properties/'.$property->id, [
                'title' => 'Unauthorized Update',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseHas('properties', [
            'id' => $property->id,
            'user_id' => $owner->id,
            'title' => 'Owner Suite',
        ]);
    }

    public function test_update_payload_cannot_change_property_owner(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $property = $this->createPropertyFor($owner);

        $response = $this
            ->actingAs($owner, 'sanctum')
            ->putJson('/api/properties/'.$property->id, [
                'title' => 'Still Owner Suite',
                'user_id' => $otherUser->id,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.title', 'Still Owner Suite')
            ->assertJsonPath('data.user_id', $owner->id);

        $this->assertDatabaseHas('properties', [
            'id' => $property->id,
            'user_id' => $owner->id,
            'title' => 'Still Owner Suite',
        ]);
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
