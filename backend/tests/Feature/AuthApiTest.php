<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_returns_token_and_user(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Luxury Guest',
            'email' => 'guest@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response
            ->assertCreated()
            ->assertJsonStructure([
                'message',
                'token',
                'user' => [
                    'id',
                    'name',
                    'email',
                    'role',
                ],
            ])
            ->assertJsonPath('message', 'User registered successfully')
            ->assertJsonPath('user.name', 'Luxury Guest')
            ->assertJsonPath('user.email', 'guest@example.com')
            ->assertJsonMissingPath('user.password')
            ->assertJsonMissingPath('user.remember_token');

        $this->assertNotEmpty($response->json('token'));
        $this->assertDatabaseHas('users', [
            'email' => 'guest@example.com',
            'role' => 'user',
        ]);
    }

    public function test_register_validation_fails_for_invalid_data(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => '',
            'email' => 'not-an-email',
            'password' => 'short',
            'password_confirmation' => 'different',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
                'email',
                'password',
            ]);
    }

    public function test_login_returns_token_and_user(): void
    {
        $user = User::factory()->create([
            'name' => 'Returning Guest',
            'email' => 'returning@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'returning@example.com',
            'password' => 'password123',
        ]);

        $response
            ->assertOk()
            ->assertJsonStructure([
                'message',
                'token',
                'user' => [
                    'id',
                    'name',
                    'email',
                    'role',
                ],
            ])
            ->assertJsonPath('message', 'Login successful')
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.email', 'returning@example.com')
            ->assertJsonMissingPath('user.password')
            ->assertJsonMissingPath('user.remember_token');

        $this->assertNotEmpty($response->json('token'));
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'locked@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'locked@example.com',
            'password' => 'wrong-password',
        ]);

        $response
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Invalid credentials')
            ->assertJsonMissingPath('token');
    }

    public function test_too_many_failed_login_attempts_returns_too_many_requests(): void
    {
        User::factory()->create([
            'email' => 'limited-login@example.com',
            'password' => Hash::make('password123'),
        ]);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this
                ->withServerVariables(['REMOTE_ADDR' => '10.10.10.10'])
                ->postJson('/api/login', [
                    'email' => 'limited-login@example.com',
                    'password' => 'wrong-password',
                ])
                ->assertUnauthorized();
        }

        $this
            ->withServerVariables(['REMOTE_ADDR' => '10.10.10.10'])
            ->postJson('/api/login', [
                'email' => 'limited-login@example.com',
                'password' => 'wrong-password',
            ])
            ->assertStatus(429);
    }

    public function test_too_many_register_attempts_returns_too_many_requests(): void
    {
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this
                ->withServerVariables(['REMOTE_ADDR' => '10.10.10.11'])
                ->postJson('/api/register', [
                    'name' => 'Limited Guest '.$attempt,
                    'email' => 'limited-register-'.$attempt.'@example.com',
                    'password' => 'password123',
                    'password_confirmation' => 'password123',
                ])
                ->assertCreated();
        }

        $this
            ->withServerVariables(['REMOTE_ADDR' => '10.10.10.11'])
            ->postJson('/api/register', [
                'name' => 'Limited Guest 4',
                'email' => 'limited-register-4@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ])
            ->assertStatus(429);
    }

    public function test_current_user_returns_safe_user_payload(): void
    {
        $user = User::factory()->create([
            'name' => 'Current Guest',
            'email' => 'current@example.com',
        ]);
        $token = $user->createToken('auth_token');

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->getJson('/api/user');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'user' => [
                    'id',
                    'name',
                    'email',
                    'role',
                ],
            ])
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.name', 'Current Guest')
            ->assertJsonPath('user.email', 'current@example.com')
            ->assertJsonPath('user.role', 'user')
            ->assertJsonMissingPath('user.password')
            ->assertJsonMissingPath('user.remember_token');
    }

    public function test_logout_revokes_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth_token');

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->postJson('/api/logout');

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Logged out successfully');

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $token->accessToken->id,
        ]);
    }
}
