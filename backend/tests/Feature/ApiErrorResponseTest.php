<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiErrorResponseTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_property_availability_returns_clean_not_found_json(): void
    {
        $response = $this
            ->withHeader('Accept', 'application/json')
            ->getJson('/api/properties/999999/availability');

        $response
            ->assertNotFound()
            ->assertExactJson([
                'message' => 'Resource not found.',
            ]);

        $this->assertResponseDoesNotExposeInternals($response->getContent());
    }

    public function test_validation_errors_keep_errors_without_debug_details(): void
    {
        $response = $this
            ->withServerVariables(['REMOTE_ADDR' => '10.20.30.40'])
            ->postJson('/api/register', []);

        $response
            ->assertUnprocessable()
            ->assertJsonStructure([
                'message',
                'errors',
            ]);

        $this->assertResponseDoesNotExposeInternals($response->getContent());
    }

    public function test_unauthenticated_api_response_is_clean(): void
    {
        $response = $this->postJson('/api/bookings', []);

        $response
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');

        $this->assertResponseDoesNotExposeInternals($response->getContent());
    }

    private function assertResponseDoesNotExposeInternals(string $content): void
    {
        $this->assertStringNotContainsString('"exception"', $content);
        $this->assertStringNotContainsString('"file"', $content);
        $this->assertStringNotContainsString('"trace"', $content);
        $this->assertStringNotContainsString('C:\\', $content);
        $this->assertStringNotContainsString('vendor\\', $content);
    }
}
