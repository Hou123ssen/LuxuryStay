<?php

namespace Tests\Feature;

use App\Models\AnalyticsEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeedDemoGeographyAnalyticsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_creates_demo_analytics_in_testing_environment(): void
    {
        $this->artisan('analytics:seed-demo-geography')
            ->assertSuccessful();

        $this->assertSame(23, User::where('email', 'like', 'geo.demo.%@luxurrstay.test')->count());
        $this->assertSame(136, AnalyticsEvent::where('metadata->source', 'local_geography_demo')->count());
        $this->assertDatabaseHas('analytics_events', [
            'event_type' => AnalyticsEvent::TYPE_USER_REGISTERED,
            'country_code' => 'MA',
            'country_name' => 'Morocco',
            'region_name' => 'Casablanca-Settat',
            'city_name' => 'Casablanca',
            'country_source' => 'local_demo',
        ]);
        $this->assertDatabaseHas('analytics_events', [
            'event_type' => AnalyticsEvent::TYPE_USER_LOGGED_IN,
            'country_code' => 'FR',
            'country_name' => 'France',
            'region_name' => 'Île-de-France',
            'city_name' => 'Paris',
            'country_source' => 'local_demo',
        ]);
    }

    public function test_command_updates_demo_user_country_and_city_summary_fields(): void
    {
        $this->artisan('analytics:seed-demo-geography')
            ->assertSuccessful();

        $user = User::where('email', 'geo.demo.ma.1@luxurrstay.test')->firstOrFail();

        $this->assertSame('user', $user->role);
        $this->assertSame('MA', $user->registered_country_code);
        $this->assertSame('Morocco', $user->registered_country_name);
        $this->assertSame('Casablanca-Settat', $user->registered_region_name);
        $this->assertSame('Casablanca', $user->registered_city_name);
        $this->assertSame('MA', $user->last_seen_country_code);
        $this->assertSame('Morocco', $user->last_seen_country_name);
        $this->assertSame('Casablanca-Settat', $user->last_seen_region_name);
        $this->assertSame('Casablanca', $user->last_seen_city_name);
        $this->assertNotNull($user->last_seen_at);
    }

    public function test_command_does_not_duplicate_demo_data_without_reset(): void
    {
        $this->artisan('analytics:seed-demo-geography')
            ->assertSuccessful();
        $this->artisan('analytics:seed-demo-geography')
            ->expectsOutput('Demo geography analytics already exists. Use --reset-demo to delete and reseed it.')
            ->assertSuccessful();

        $this->assertSame(23, User::where('email', 'like', 'geo.demo.%@luxurrstay.test')->count());
        $this->assertSame(136, AnalyticsEvent::where('metadata->source', 'local_geography_demo')->count());
    }

    public function test_reset_demo_deletes_and_reseeds_only_demo_data(): void
    {
        $realUser = User::factory()->create(['email' => 'real.geography@example.com']);
        $realEvent = AnalyticsEvent::create([
            'user_id' => $realUser->id,
            'event_type' => AnalyticsEvent::TYPE_USER_LOGGED_IN,
            'country_code' => 'MA',
            'country_name' => 'Morocco',
            'country_source' => 'CF-IPCountry',
            'region_name' => 'Casablanca-Settat',
            'city_name' => 'Casablanca',
            'ip_hash' => 'non-demo-ip-hash',
            'user_agent_hash' => 'non-demo-agent-hash',
            'metadata' => ['source' => 'api'],
            'occurred_at' => now(),
        ]);

        $this->artisan('analytics:seed-demo-geography')
            ->assertSuccessful();
        $this->artisan('analytics:seed-demo-geography --reset-demo')
            ->assertSuccessful();

        $this->assertDatabaseHas('users', ['id' => $realUser->id, 'email' => 'real.geography@example.com']);
        $this->assertDatabaseHas('analytics_events', ['id' => $realEvent->id, 'metadata' => json_encode(['source' => 'api'])]);
        $this->assertSame(23, User::where('email', 'like', 'geo.demo.%@luxurrstay.test')->count());
        $this->assertSame(136, AnalyticsEvent::where('metadata->source', 'local_geography_demo')->count());
    }

    public function test_command_refuses_production_environment(): void
    {
        config(['app.env' => 'production']);

        $this->artisan('analytics:seed-demo-geography')
            ->expectsOutput('Refusing to seed demo geography analytics in production.')
            ->assertFailed();

        $this->assertSame(0, User::where('email', 'like', 'geo.demo.%@luxurrstay.test')->count());
        $this->assertSame(0, AnalyticsEvent::where('metadata->source', 'local_geography_demo')->count());
    }

    public function test_admin_geography_endpoint_shows_seeded_countries_and_cities_without_private_data(): void
    {
        $this->artisan('analytics:seed-demo-geography')
            ->assertSuccessful();

        $json = $this
            ->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum')
            ->getJson('/api/admin/dashboard/geography?days=all')
            ->assertOk()
            ->assertJsonPath('data.summary.usage_events_count', 136)
            ->getContent();

        $payload = json_decode($json, true);
        $countries = collect($payload['data']['usage_events_by_country'])->keyBy('country_code');
        $cities = collect($payload['data']['usage_events_by_city'])->keyBy('city_name');

        foreach (['MA', 'FR', 'ES', 'BE', 'US', 'GB', 'DE', 'IT', 'NL', 'CA'] as $countryCode) {
            $this->assertTrue($countries->has($countryCode), "Missing {$countryCode} from seeded geography payload.");
        }

        foreach (['Casablanca', 'Rabat', 'Marrakech', 'Paris', 'Madrid', 'Brussels', 'New York', 'London', 'Berlin', 'Rome', 'Amsterdam', 'Toronto'] as $cityName) {
            $this->assertTrue($cities->has($cityName), "Missing {$cityName} from seeded geography payload.");
        }

        $this->assertGreaterThan($countries['FR']['count'], $countries['MA']['count']);
        $this->assertGreaterThan($countries['ES']['count'], $countries['MA']['count']);

        $this->assertStringNotContainsString('geo.demo.ma.1@luxurrstay.test', $json);
        $this->assertStringNotContainsString('email', $json);
        $this->assertStringNotContainsString('phone', $json);
        $this->assertStringNotContainsString('ip_hash', $json);
        $this->assertStringNotContainsString('user_agent_hash', $json);
        $this->assertStringNotContainsString('demo-geography-ip', $json);
        $this->assertStringNotContainsString('demo-geography-agent', $json);
        $this->assertStringNotContainsString('gps', strtolower($json));
        $this->assertStringNotContainsString('address', strtolower($json));
    }

    public function test_seeded_events_are_spread_across_supported_period_filters(): void
    {
        $this->artisan('analytics:seed-demo-geography')
            ->assertSuccessful();

        $admin = User::factory()->create(['role' => 'admin']);

        $sevenDays = $this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/dashboard/geography?days=7')
            ->assertOk()
            ->json('data.summary.usage_events_count');

        $thirtyDays = $this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/dashboard/geography?days=30')
            ->assertOk()
            ->json('data.summary.usage_events_count');

        $ninetyDays = $this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/dashboard/geography?days=90')
            ->assertOk()
            ->json('data.summary.usage_events_count');

        $all = $this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/dashboard/geography?days=all')
            ->assertOk()
            ->json('data.summary.usage_events_count');

        $this->assertGreaterThan(0, $sevenDays);
        $this->assertGreaterThan($sevenDays, $thirtyDays);
        $this->assertGreaterThan($thirtyDays, $ninetyDays);
        $this->assertSame($ninetyDays, $all);
    }
}
