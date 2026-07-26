<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_property_owner_can_upload_image(): void
    {
        Storage::fake('public');

        $owner = User::factory()->create();
        $property = $this->createPropertyFor($owner);

        $response = $this
            ->actingAs($owner, 'sanctum')
            ->postJson('/api/images', [
                'property_id' => $property->id,
                'images' => [
                    UploadedFile::fake()->image('suite.jpg'),
                ],
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('message', 'Images uploaded successfully')
            ->assertJsonStructure(['images']);

        $path = $response->json('images.0');

        $this->assertIsString($path);
        $this->assertStringStartsWith('images/', $path);

        $this->assertDatabaseHas('images', [
            'property_id' => $property->id,
            'path' => $path,
        ]);
        Storage::disk('public')->assertExists($path);
    }

    public function test_property_detail_images_include_path_and_url(): void
    {
        $owner = User::factory()->create();
        $property = $this->createPropertyFor($owner);

        $property->images()->create([
            'path' => 'images/suite.jpg',
        ]);

        $response = $this
            ->actingAs($owner, 'sanctum')
            ->getJson('http://127.0.0.1:8000/api/properties/'.$property->id);

        $response
            ->assertOk()
            ->assertJsonPath('images.0.path', 'images/suite.jpg');

        $url = $response->json('images.0.url');

        $this->assertIsString($url);
        $this->assertStringStartsWith('http://127.0.0.1:8000/storage/images/', $url);
        $this->assertStringContainsString('/storage/images/suite.jpg', $url);
    }

    public function test_property_list_images_include_path_and_url(): void
    {
        $owner = User::factory()->create();
        $property = $this->createPropertyFor($owner);

        $property->images()->create([
            'path' => 'images/listing.jpg',
        ]);

        $response = $this
            ->actingAs($owner, 'sanctum')
            ->getJson('http://127.0.0.1:8000/api/properties');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.images.0.path', 'images/listing.jpg');

        $url = $response->json('data.0.images.0.url');

        $this->assertIsString($url);
        $this->assertStringStartsWith('http://127.0.0.1:8000/storage/images/', $url);
        $this->assertStringContainsString('/storage/images/listing.jpg', $url);
    }

    public function test_authenticated_non_owner_cannot_upload_image(): void
    {
        Storage::fake('public');

        $owner = User::factory()->create();
        $nonOwner = User::factory()->create();
        $property = $this->createPropertyFor($owner);

        $response = $this
            ->actingAs($nonOwner, 'sanctum')
            ->postJson('/api/images', [
                'property_id' => $property->id,
                'images' => [
                    UploadedFile::fake()->image('suite.jpg'),
                ],
            ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('images', [
            'property_id' => $property->id,
        ]);
        $this->assertSame([], Storage::disk('public')->allFiles('images'));
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
