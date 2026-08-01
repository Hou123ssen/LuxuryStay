<?php

namespace Tests\Feature;

use App\Models\AnalyticsEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminGeographyAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_geography_endpoint(): void
    {
        $this->getJson('/api/admin/dashboard/geography')->assertUnauthorized();
    }

    public function test_normal_user_cannot_access_geography_endpoint(): void
    {
        $this
            ->actingAs(User::factory()->create(), 'sanctum')
            ->getJson('/api/admin/dashboard/geography')
            ->assertForbidden()
            ->assertJsonPath('message', 'This action is unauthorized.');
    }

    public function test_admin_can_access_geography_endpoint(): void
    {
        $this
            ->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/admin/dashboard/geography')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'summary',
                    'users_by_registered_country',
                    'users_by_last_seen_country',
                    'usage_events_by_country',
                    'login_events_by_country',
                    'registration_events_by_country',
                    'recent_country_activity',
                ],
            ]);
    }

    public function test_registration_records_analytics_event_and_user_country_fields(): void
    {
        $this
            ->withHeader('X-LuxurrStay-Test-Country', 'MA')
            ->postJson('/api/register', $this->registrationPayload('country.register@gmail.com'))
            ->assertCreated()
            ->assertJsonPath('user.email', 'country.register@gmail.com')
            ->assertJsonMissingPath('user.registered_country_code');

        $user = User::where('email', 'country.register@gmail.com')->firstOrFail();

        $this->assertSame('MA', $user->registered_country_code);
        $this->assertSame('Morocco', $user->registered_country_name);
        $this->assertSame('MA', $user->last_seen_country_code);
        $this->assertSame('Morocco', $user->last_seen_country_name);
        $this->assertNotNull($user->last_seen_at);

        $this->assertDatabaseHas('analytics_events', [
            'user_id' => $user->id,
            'event_type' => AnalyticsEvent::TYPE_USER_REGISTERED,
            'country_code' => 'MA',
            'country_name' => 'Morocco',
            'country_source' => 'X-LuxurrStay-Test-Country',
        ]);
    }

    public function test_login_records_analytics_event_and_updates_last_seen_country(): void
    {
        $user = User::factory()->create([
            'email' => 'country.login@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this
            ->withHeader('X-LuxurrStay-Test-Country', 'FR')
            ->postJson('/api/login', [
                'email' => 'country.login@example.com',
                'password' => 'password123',
            ])
            ->assertOk()
            ->assertJsonPath('user.email', 'country.login@example.com');

        $user->refresh();

        $this->assertSame('FR', $user->last_seen_country_code);
        $this->assertSame('France', $user->last_seen_country_name);
        $this->assertNotNull($user->last_seen_at);

        $this->assertDatabaseHas('analytics_events', [
            'user_id' => $user->id,
            'event_type' => AnalyticsEvent::TYPE_USER_LOGGED_IN,
            'country_code' => 'FR',
            'country_name' => 'France',
            'country_source' => 'X-LuxurrStay-Test-Country',
        ]);
    }

    public function test_invalid_country_header_becomes_unknown_safely(): void
    {
        $this
            ->withHeader('X-LuxurrStay-Test-Country', 'INVALID')
            ->postJson('/api/register', $this->registrationPayload('unknown.country@gmail.com'))
            ->assertCreated();

        $user = User::where('email', 'unknown.country@gmail.com')->firstOrFail();

        $this->assertNull($user->registered_country_code);
        $this->assertSame('Unknown', $user->registered_country_name);
        $this->assertDatabaseHas('analytics_events', [
            'user_id' => $user->id,
            'event_type' => AnalyticsEvent::TYPE_USER_REGISTERED,
            'country_code' => null,
            'country_name' => 'Unknown',
            'country_source' => null,
        ]);
    }

    public function test_production_environment_does_not_trust_local_testing_country_header(): void
    {
        config(['app.env' => 'production']);

        $this
            ->withHeader('X-LuxurrStay-Test-Country', 'MA')
            ->postJson('/api/register', $this->registrationPayload('production.country@gmail.com'))
            ->assertCreated();

        $user = User::where('email', 'production.country@gmail.com')->firstOrFail();

        $this->assertNull($user->registered_country_code);
        $this->assertSame('Unknown', $user->registered_country_name);
    }

    public function test_admin_geography_groups_users_and_events_by_country(): void
    {
        $admin = $this->admin();
        $moroccoUser = $this->userWithCountries('MA', 'Morocco', 'FR', 'France');
        $franceUser = $this->userWithCountries('FR', 'France', 'FR', 'France');

        $this->eventFor($moroccoUser, AnalyticsEvent::TYPE_USER_REGISTERED, 'MA', 'Morocco');
        $this->eventFor($moroccoUser, AnalyticsEvent::TYPE_USER_LOGGED_IN, 'FR', 'France');
        $this->eventFor($franceUser, AnalyticsEvent::TYPE_USER_REGISTERED, 'FR', 'France');

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/dashboard/geography')
            ->assertOk()
            ->assertJsonPath('data.summary.known_registered_country_users_count', 2)
            ->assertJsonPath('data.summary.known_last_seen_country_users_count', 2)
            ->assertJsonPath('data.summary.usage_events_count', 3);

        $registered = collect($response->json('data.users_by_registered_country'))->keyBy('country_code');
        $lastSeen = collect($response->json('data.users_by_last_seen_country'))->keyBy('country_code');
        $usageEvents = collect($response->json('data.usage_events_by_country'))->keyBy('country_code');
        $loginEvents = collect($response->json('data.login_events_by_country'))->keyBy('country_code');
        $registrationEvents = collect($response->json('data.registration_events_by_country'))->keyBy('country_code');

        $this->assertSame(1, $registered['MA']['count']);
        $this->assertSame(1, $registered['FR']['count']);
        $this->assertSame(2, $lastSeen['FR']['count']);
        $this->assertSame(2, $usageEvents['FR']['count']);
        $this->assertSame(1, $usageEvents['MA']['count']);
        $this->assertSame(1, $loginEvents['FR']['count']);
        $this->assertSame(1, $registrationEvents['MA']['count']);
        $this->assertSame(1, $registrationEvents['FR']['count']);
    }

    public function test_days_filter_limits_event_counts(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();

        $this->eventFor($user, AnalyticsEvent::TYPE_USER_LOGGED_IN, 'MA', 'Morocco', now()->subDays(3));
        $this->eventFor($user, AnalyticsEvent::TYPE_USER_LOGGED_IN, 'FR', 'France', now()->subDays(20));
        $this->eventFor($user, AnalyticsEvent::TYPE_USER_LOGGED_IN, 'US', 'United States', now()->subDays(80));
        $this->eventFor($user, AnalyticsEvent::TYPE_USER_LOGGED_IN, 'CA', 'Canada', now()->subDays(120));

        $this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/dashboard/geography?days=7')
            ->assertOk()
            ->assertJsonPath('data.summary.usage_events_count', 1);

        $this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/dashboard/geography?days=30')
            ->assertOk()
            ->assertJsonPath('data.summary.usage_events_count', 2);

        $this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/dashboard/geography?days=90')
            ->assertOk()
            ->assertJsonPath('data.summary.usage_events_count', 3);

        $this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/dashboard/geography?days=all')
            ->assertOk()
            ->assertJsonPath('data.summary.usage_events_count', 4);

        $this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/dashboard/geography?days=invalid')
            ->assertOk()
            ->assertJsonPath('data.summary.usage_events_count', 2);
    }

    public function test_geography_endpoint_does_not_expose_private_analytics_or_user_fields(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create([
            'email' => 'private.analytics@example.com',
            'phone' => '555-0100',
        ]);

        AnalyticsEvent::create([
            'user_id' => $user->id,
            'event_type' => AnalyticsEvent::TYPE_USER_LOGGED_IN,
            'country_code' => 'MA',
            'country_name' => 'Morocco',
            'country_source' => 'CF-IPCountry',
            'ip_hash' => 'private-ip-hash',
            'user_agent_hash' => 'private-user-agent-hash',
            'metadata' => ['source' => 'api'],
            'occurred_at' => now(),
        ]);

        $json = $this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/dashboard/geography')
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('private.analytics@example.com', $json);
        $this->assertStringNotContainsString('555-0100', $json);
        $this->assertStringNotContainsString('email', $json);
        $this->assertStringNotContainsString('phone', $json);
        $this->assertStringNotContainsString('ip_hash', $json);
        $this->assertStringNotContainsString('user_agent_hash', $json);
        $this->assertStringNotContainsString('private-ip-hash', $json);
        $this->assertStringNotContainsString('private-user-agent-hash', $json);
    }

    public function test_auth_still_works_when_country_cannot_be_resolved(): void
    {
        $this
            ->withHeader('X-LuxurrStay-Test-Country', 'ZZ')
            ->postJson('/api/register', $this->registrationPayload('safe.auth@gmail.com'))
            ->assertCreated()
            ->assertJsonPath('user.email', 'safe.auth@gmail.com');
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function registrationPayload(string $email): array
    {
        return [
            'name' => 'Country Guest',
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];
    }

    private function userWithCountries(
        ?string $registeredCode,
        ?string $registeredName,
        ?string $lastSeenCode,
        ?string $lastSeenName
    ): User {
        $user = User::factory()->create();
        $user->forceFill([
            'registered_country_code' => $registeredCode,
            'registered_country_name' => $registeredName,
            'last_seen_country_code' => $lastSeenCode,
            'last_seen_country_name' => $lastSeenName,
            'last_seen_at' => now(),
        ])->save();

        return $user;
    }

    private function eventFor(
        User $user,
        string $eventType,
        ?string $countryCode,
        string $countryName,
        $occurredAt = null
    ): AnalyticsEvent {
        return AnalyticsEvent::create([
            'user_id' => $user->id,
            'event_type' => $eventType,
            'country_code' => $countryCode,
            'country_name' => $countryName,
            'country_source' => 'CF-IPCountry',
            'ip_hash' => hash('sha256', '127.0.0.1'),
            'user_agent_hash' => hash('sha256', 'Test Browser'),
            'metadata' => ['source' => 'test'],
            'occurred_at' => $occurredAt ?? now(),
        ]);
    }
}
