<?php

namespace Tests\Feature;

use App\Models\AnalyticsEvent;
use App\Models\User;
use App\Services\Analytics\DemoAnalyticsDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoAnalyticsDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_detection_service_counts_demo_users_and_events(): void
    {
        $this->artisan('analytics:seed-demo-geography')->assertSuccessful();

        $counts = app(DemoAnalyticsDataService::class)->counts();

        $this->assertSame(23, $counts['demo_users_count']);
        $this->assertSame(136, $counts['demo_events_count']);
        $this->assertSame(23, $counts['demo_registration_events_count']);
        $this->assertSame(113, $counts['demo_login_events_count']);
    }

    public function test_clear_demo_command_deletes_only_demo_data(): void
    {
        $realUser = User::factory()->create(['email' => 'real.user@example.test']);
        $realEvent = AnalyticsEvent::create([
            'user_id' => $realUser->id,
            'event_type' => AnalyticsEvent::TYPE_USER_LOGGED_IN,
            'country_code' => 'MA',
            'country_name' => 'Morocco',
            'country_source' => 'test',
            'metadata' => ['source' => 'real'],
            'occurred_at' => now(),
        ]);

        $this->artisan('analytics:seed-demo-geography')->assertSuccessful();

        $this->artisan('analytics:clear-demo-geography')
            ->expectsOutput('Deleted 23 demo user(s).')
            ->expectsOutput('Deleted 136 demo analytics event(s).')
            ->assertSuccessful();

        $this->assertDatabaseHas('users', ['id' => $realUser->id, 'email' => 'real.user@example.test']);
        $this->assertDatabaseHas('analytics_events', ['id' => $realEvent->id]);
        $this->assertSame(0, User::where('email', 'like', 'geo.demo.%@luxurrstay.test')->count());
        $this->assertSame(0, AnalyticsEvent::where('metadata->source', 'local_geography_demo')->count());
    }

    public function test_clear_demo_command_refuses_production_environment(): void
    {
        config(['app.env' => 'production']);

        $this->artisan('analytics:clear-demo-geography')
            ->expectsOutput('Refusing to clear demo geography analytics in production.')
            ->assertFailed();
    }

    public function test_overview_returns_demo_meta_and_can_exclude_demo_users(): void
    {
        $this->artisan('analytics:seed-demo-geography')->assertSuccessful();
        $admin = User::factory()->create(['role' => 'admin']);

        $this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/dashboard/overview?include_demo=true')
            ->assertOk()
            ->assertJsonPath('meta.demo_data.included', true)
            ->assertJsonPath('meta.demo_data.available', true)
            ->assertJsonPath('meta.demo_data.demo_users_count', 23)
            ->assertJsonPath('data.totals.users_count', 24);

        $this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/dashboard/overview?include_demo=false')
            ->assertOk()
            ->assertJsonPath('meta.demo_data.included', false)
            ->assertJsonPath('data.totals.users_count', 1);
    }

    public function test_geography_and_charts_can_exclude_demo_analytics_events(): void
    {
        $this->artisan('analytics:seed-demo-geography')->assertSuccessful();
        $admin = User::factory()->create(['role' => 'admin']);

        $this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/dashboard/geography?days=all&include_demo=true')
            ->assertOk()
            ->assertJsonPath('meta.demo_data.included', true)
            ->assertJsonPath('data.summary.usage_events_count', 136);

        $this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/dashboard/geography?days=all&include_demo=false')
            ->assertOk()
            ->assertJsonPath('meta.demo_data.included', false)
            ->assertJsonPath('data.summary.usage_events_count', 0);

        $this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/dashboard/charts?days=all&include_demo=true')
            ->assertOk()
            ->assertJsonPath('meta.demo_data.included', true)
            ->assertJsonPath('data.totals.registrations', 23)
            ->assertJsonPath('data.totals.logins', 113);

        $this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/dashboard/charts?days=all&include_demo=false')
            ->assertOk()
            ->assertJsonPath('meta.demo_data.included', false)
            ->assertJsonPath('data.totals.registrations', 1)
            ->assertJsonPath('data.totals.logins', 0);
    }

    public function test_admin_endpoints_default_to_excluding_demo_data_in_production(): void
    {
        $this->artisan('analytics:seed-demo-geography')->assertSuccessful();
        $admin = User::factory()->create(['role' => 'admin']);
        config(['app.env' => 'production']);

        $this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/dashboard/geography?days=all')
            ->assertOk()
            ->assertJsonPath('meta.demo_data.included', false)
            ->assertJsonPath('data.summary.usage_events_count', 0);
    }

    public function test_demo_meta_does_not_expose_private_demo_fields(): void
    {
        $this->artisan('analytics:seed-demo-geography')->assertSuccessful();
        $admin = User::factory()->create(['role' => 'admin']);

        $json = $this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/dashboard/overview?include_demo=true')
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('geo.demo.ma.1@luxurrstay.test', $json);
        $this->assertStringNotContainsString('ip_hash', $json);
        $this->assertStringNotContainsString('user_agent_hash', $json);
        $this->assertStringNotContainsString('demo-geography-ip', $json);
        $this->assertStringNotContainsString('demo-geography-agent', $json);
        $this->assertStringNotContainsString('phone', $json);
        $this->assertStringNotContainsString('gps', strtolower($json));
        $this->assertStringNotContainsString('address', strtolower($json));
    }
}
