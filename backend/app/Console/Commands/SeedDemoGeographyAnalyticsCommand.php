<?php

namespace App\Console\Commands;

use App\Models\AnalyticsEvent;
use App\Models\User;
use App\Services\Analytics\AnalyticsHashService;
use App\Services\Analytics\DemoAnalyticsDataService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class SeedDemoGeographyAnalyticsCommand extends Command
{
    private const DEMO_SOURCE = 'local_geography_demo';

    protected $signature = 'analytics:seed-demo-geography {--reset-demo : Delete existing demo geography analytics data before seeding}';

    protected $description = 'Seed local/testing-only demo geography analytics for the admin world map.';

    public function handle(AnalyticsHashService $hashService): int
    {
        if ($this->isProduction()) {
            $this->error('Refusing to seed demo geography analytics in production.');

            return self::FAILURE;
        }

        if ($this->option('reset-demo')) {
            $deleted = $this->deleteDemoData();
            $this->info("Deleted {$deleted['events']} demo analytics event(s) and {$deleted['users']} demo user(s).");
        } elseif ($this->demoDataExists()) {
            $this->warn('Demo geography analytics already exists. Use --reset-demo to delete and reseed it.');

            return self::SUCCESS;
        }

        $created = DB::transaction(fn () => $this->seedDemoData($hashService));

        $this->info("Seeded {$created['users']} demo user(s) and {$created['events']} demo analytics event(s).");

        return self::SUCCESS;
    }

    private function seedDemoData(AnalyticsHashService $hashService): array
    {
        $users = 0;
        $events = 0;

        foreach ($this->countries() as $country) {
            for ($index = 1; $index <= $country['users']; $index++) {
                $city = $country['cities'][($index - 1) % count($country['cities'])];
                $email = sprintf('geo.demo.%s.%d@luxurrstay.test', strtolower($country['code']), $index);
                $user = $this->createDemoUser($country, $city, $email, $index);
                $users++;

                foreach ($this->eventSchedule($country['events'], $index) as $eventIndex => $occurredAt) {
                    $eventType = $eventIndex === 0
                        ? AnalyticsEvent::TYPE_USER_REGISTERED
                        : AnalyticsEvent::TYPE_USER_LOGGED_IN;

                    AnalyticsEvent::create([
                        'user_id' => $user->id,
                        'event_type' => $eventType,
                        'country_code' => $country['code'],
                        'country_name' => $country['name'],
                        'country_source' => 'local_demo',
                        'region_name' => $city['region'],
                        'city_name' => $city['name'],
                        'ip_hash' => $hashService->hash('demo-geography-ip:'.$country['code'].':'.$index),
                        'user_agent_hash' => $hashService->hash('demo-geography-agent:'.$country['code'].':'.$index),
                        'metadata' => $this->demoMetadata(),
                        'occurred_at' => $occurredAt,
                    ]);

                    $events++;
                }
            }
        }

        return ['users' => $users, 'events' => $events];
    }

    private function createDemoUser(array $country, array $city, string $email, int $index): User
    {
        $user = User::create([
            'name' => sprintf('Geo Demo %s %d', $country['code'], $index),
            'email' => $email,
            'password' => Hash::make(str()->random(32)),
        ]);

        $user->forceFill([
            'role' => 'user',
            'registered_country_code' => $country['code'],
            'registered_country_name' => $country['name'],
            'registered_region_name' => $city['region'],
            'registered_city_name' => $city['name'],
            'last_seen_country_code' => $country['code'],
            'last_seen_country_name' => $country['name'],
            'last_seen_region_name' => $city['region'],
            'last_seen_city_name' => $city['name'],
            'last_seen_at' => now(),
        ])->save();

        return $user;
    }

    private function eventSchedule(int $eventCount, int $userIndex): array
    {
        $days = [1, 2, 4, 8, 14, 24, 36, 55, 72, 86];

        return collect(range(0, $eventCount - 1))
            ->map(fn (int $index): Carbon => now()
                ->subDays($days[$index % count($days)])
                ->subHours(($userIndex + $index) % 18))
            ->all();
    }

    private function deleteDemoData(): array
    {
        return app(DemoAnalyticsDataService::class)->deleteDemoData();
    }

    private function demoDataExists(): bool
    {
        $hasEvents = Schema::hasTable('analytics_events')
            && AnalyticsEvent::query()
                ->where('metadata->demo', true)
                ->where('metadata->source', self::DEMO_SOURCE)
                ->exists();

        $hasUsers = Schema::hasTable('users')
            && User::query()
                ->where('email', 'like', 'geo.demo.%@luxurrstay.test')
                ->exists();

        return $hasEvents || $hasUsers;
    }

    private function demoMetadata(): array
    {
        return [
            'demo' => true,
            'source' => self::DEMO_SOURCE,
        ];
    }

    private function isProduction(): bool
    {
        return app()->environment('production') || config('app.env') === 'production';
    }

    private function countries(): array
    {
        return [
            [
                'code' => 'MA',
                'name' => 'Morocco',
                'users' => 6,
                'events' => 10,
                'cities' => [
                    ['name' => 'Casablanca', 'region' => 'Casablanca-Settat'],
                    ['name' => 'Rabat', 'region' => 'Rabat-Salé-Kénitra'],
                    ['name' => 'Marrakech', 'region' => 'Marrakech-Safi'],
                ],
            ],
            [
                'code' => 'FR',
                'name' => 'France',
                'users' => 3,
                'events' => 7,
                'cities' => [
                    ['name' => 'Paris', 'region' => 'Île-de-France'],
                ],
            ],
            [
                'code' => 'ES',
                'name' => 'Spain',
                'users' => 3,
                'events' => 6,
                'cities' => [
                    ['name' => 'Madrid', 'region' => 'Community of Madrid'],
                ],
            ],
            [
                'code' => 'BE',
                'name' => 'Belgium',
                'users' => 2,
                'events' => 4,
                'cities' => [
                    ['name' => 'Brussels', 'region' => 'Brussels-Capital'],
                ],
            ],
            [
                'code' => 'US',
                'name' => 'United States',
                'users' => 2,
                'events' => 4,
                'cities' => [
                    ['name' => 'New York', 'region' => 'New York'],
                ],
            ],
            [
                'code' => 'GB',
                'name' => 'United Kingdom',
                'users' => 2,
                'events' => 3,
                'cities' => [
                    ['name' => 'London', 'region' => 'England'],
                ],
            ],
            [
                'code' => 'DE',
                'name' => 'Germany',
                'users' => 2,
                'events' => 3,
                'cities' => [
                    ['name' => 'Berlin', 'region' => 'Berlin'],
                ],
            ],
            [
                'code' => 'IT',
                'name' => 'Italy',
                'users' => 1,
                'events' => 3,
                'cities' => [
                    ['name' => 'Rome', 'region' => 'Lazio'],
                ],
            ],
            [
                'code' => 'NL',
                'name' => 'Netherlands',
                'users' => 1,
                'events' => 3,
                'cities' => [
                    ['name' => 'Amsterdam', 'region' => 'North Holland'],
                ],
            ],
            [
                'code' => 'CA',
                'name' => 'Canada',
                'users' => 1,
                'events' => 3,
                'cities' => [
                    ['name' => 'Toronto', 'region' => 'Ontario'],
                ],
            ],
        ];
    }
}
