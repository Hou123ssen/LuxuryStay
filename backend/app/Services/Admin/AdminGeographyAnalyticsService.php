<?php

namespace App\Services\Admin;

use App\Models\AnalyticsEvent;
use App\Models\User;
use App\Services\Analytics\DemoAnalyticsDataService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class AdminGeographyAnalyticsService
{
    public function __construct(private readonly DemoAnalyticsDataService $demoData)
    {
    }

    public function overview(Request $request, bool $includeDemo = true): array
    {
        $days = $this->days($request);

        return [
            'summary' => $this->summary($days, $includeDemo),
            'users_by_registered_country' => $this->usersByCountry('registered_country_code', 'registered_country_name', $includeDemo),
            'users_by_last_seen_country' => $this->usersByCountry('last_seen_country_code', 'last_seen_country_name', $includeDemo),
            'usage_events_by_country' => $this->eventsByCountry($days, $includeDemo),
            'login_events_by_country' => $this->eventsByCountry($days, $includeDemo, AnalyticsEvent::TYPE_USER_LOGGED_IN),
            'registration_events_by_country' => $this->eventsByCountry($days, $includeDemo, AnalyticsEvent::TYPE_USER_REGISTERED),
            'users_by_registered_city' => $this->usersByCity('registered_city_name', 'registered_region_name', 'registered_country_code', 'registered_country_name', $includeDemo),
            'users_by_last_seen_city' => $this->usersByCity('last_seen_city_name', 'last_seen_region_name', 'last_seen_country_code', 'last_seen_country_name', $includeDemo),
            'usage_events_by_city' => $this->eventsByCity($days, $includeDemo),
            'login_events_by_city' => $this->eventsByCity($days, $includeDemo, AnalyticsEvent::TYPE_USER_LOGGED_IN),
            'registration_events_by_city' => $this->eventsByCity($days, $includeDemo, AnalyticsEvent::TYPE_USER_REGISTERED),
            'recent_country_activity' => $this->recentCountryActivity($days, $includeDemo),
        ];
    }

    private function summary(?int $days, bool $includeDemo): array
    {
        return [
            'known_registered_country_users_count' => $this->knownUserCountryCount('registered_country_code', $includeDemo),
            'unknown_registered_country_users_count' => $this->unknownUserCountryCount('registered_country_code', $includeDemo),
            'known_last_seen_country_users_count' => $this->knownUserCountryCount('last_seen_country_code', $includeDemo),
            'unknown_last_seen_country_users_count' => $this->unknownUserCountryCount('last_seen_country_code', $includeDemo),
            'known_registered_city_users_count' => $this->knownUserLocationCount('registered_city_name', $includeDemo),
            'unknown_registered_city_users_count' => $this->unknownUserLocationCount('registered_city_name', $includeDemo),
            'known_last_seen_city_users_count' => $this->knownUserLocationCount('last_seen_city_name', $includeDemo),
            'unknown_last_seen_city_users_count' => $this->unknownUserLocationCount('last_seen_city_name', $includeDemo),
            'usage_events_count' => $this->hasAnalyticsEventsTable()
                ? $this->eventsBaseQuery($days, $includeDemo)->count()
                : 0,
        ];
    }

    private function usersByCountry(string $codeColumn, string $nameColumn, bool $includeDemo): array
    {
        if (! $this->hasUsersCountryColumns($codeColumn, $nameColumn)) {
            return [];
        }

        return $this->demoData->usersQuery($includeDemo)
            ->selectRaw("$codeColumn as country_code")
            ->selectRaw("$nameColumn as country_name")
            ->selectRaw('COUNT(*) as aggregate')
            ->groupBy($codeColumn, $nameColumn)
            ->orderByDesc('aggregate')
            ->get()
            ->map(fn ($row) => $this->countryRow($row->country_code, $row->country_name, (int) $row->aggregate))
            ->all();
    }

    private function usersByCity(string $cityColumn, string $regionColumn, string $countryCodeColumn, string $countryNameColumn, bool $includeDemo): array
    {
        if (! $this->hasUsersCityColumns($cityColumn, $regionColumn, $countryCodeColumn, $countryNameColumn)) {
            return [];
        }

        return $this->demoData->usersQuery($includeDemo)
            ->selectRaw("$cityColumn as city_name")
            ->selectRaw("$regionColumn as region_name")
            ->selectRaw("$countryCodeColumn as country_code")
            ->selectRaw("$countryNameColumn as country_name")
            ->selectRaw('COUNT(*) as aggregate')
            ->groupBy($cityColumn, $regionColumn, $countryCodeColumn, $countryNameColumn)
            ->orderByDesc('aggregate')
            ->get()
            ->map(fn ($row) => $this->cityRow(
                $row->city_name,
                $row->region_name,
                $row->country_code,
                $row->country_name,
                (int) $row->aggregate
            ))
            ->all();
    }

    private function eventsByCountry(?int $days, bool $includeDemo, ?string $eventType = null): array
    {
        if (! $this->hasAnalyticsEventsTable()) {
            return [];
        }

        $query = $this->eventsBaseQuery($days, $includeDemo);

        if ($eventType !== null) {
            $query->where('event_type', $eventType);
        }

        return $query
            ->select('country_code', 'country_name')
            ->selectRaw('COUNT(*) as aggregate')
            ->groupBy('country_code', 'country_name')
            ->orderByDesc('aggregate')
            ->get()
            ->map(fn ($row) => $this->countryRow($row->country_code, $row->country_name, (int) $row->aggregate))
            ->all();
    }

    private function eventsByCity(?int $days, bool $includeDemo, ?string $eventType = null): array
    {
        if (! $this->hasAnalyticsEventCityColumns()) {
            return [];
        }

        $query = $this->eventsBaseQuery($days, $includeDemo);

        if ($eventType !== null) {
            $query->where('event_type', $eventType);
        }

        return $query
            ->select('city_name', 'region_name', 'country_code', 'country_name')
            ->selectRaw('COUNT(*) as aggregate')
            ->groupBy('city_name', 'region_name', 'country_code', 'country_name')
            ->orderByDesc('aggregate')
            ->get()
            ->map(fn ($row) => $this->cityRow(
                $row->city_name,
                $row->region_name,
                $row->country_code,
                $row->country_name,
                (int) $row->aggregate
            ))
            ->all();
    }

    private function recentCountryActivity(?int $days, bool $includeDemo): array
    {
        if (! $this->hasAnalyticsEventsTable()) {
            return [];
        }

        $columns = ['id', 'event_type', 'user_id', 'country_code', 'country_name', 'occurred_at'];

        if ($this->hasAnalyticsEventCityColumns()) {
            $columns = ['id', 'event_type', 'user_id', 'country_code', 'country_name', 'region_name', 'city_name', 'occurred_at'];
        }

        return $this->eventsBaseQuery($days, $includeDemo)
            ->latest('occurred_at')
            ->limit(10)
            ->get($columns)
            ->map(fn (AnalyticsEvent $event) => [
                'id' => $event->id,
                'event_type' => $event->event_type,
                'user_id' => $event->user_id,
                'country_code' => $event->country_code,
                'country_name' => $event->country_name ?? 'Unknown',
                'region_name' => $event->getAttribute('region_name') ?? 'Unknown',
                'city_name' => $event->getAttribute('city_name') ?? 'Unknown',
                'occurred_at' => $event->occurred_at?->toJSON(),
            ])
            ->all();
    }

    private function eventsBaseQuery(?int $days, bool $includeDemo): Builder
    {
        $query = $this->demoData->eventsQuery($includeDemo);

        if ($days !== null) {
            $query->where('occurred_at', '>=', now()->subDays($days));
        }

        return $query;
    }

    private function knownUserCountryCount(string $codeColumn, bool $includeDemo): int
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', $codeColumn)) {
            return 0;
        }

        return $this->demoData->usersQuery($includeDemo)->whereNotNull($codeColumn)->count();
    }

    private function unknownUserCountryCount(string $codeColumn, bool $includeDemo): int
    {
        if (! Schema::hasTable('users')) {
            return 0;
        }

        if (! Schema::hasColumn('users', $codeColumn)) {
            return $this->demoData->usersQuery($includeDemo)->count();
        }

        return $this->demoData->usersQuery($includeDemo)->whereNull($codeColumn)->count();
    }

    private function knownUserLocationCount(string $column, bool $includeDemo): int
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', $column)) {
            return 0;
        }

        return $this->demoData->usersQuery($includeDemo)
            ->whereNotNull($column)
            ->where($column, '!=', 'Unknown')
            ->count();
    }

    private function unknownUserLocationCount(string $column, bool $includeDemo): int
    {
        if (! Schema::hasTable('users')) {
            return 0;
        }

        if (! Schema::hasColumn('users', $column)) {
            return $this->demoData->usersQuery($includeDemo)->count();
        }

        return $this->demoData->usersQuery($includeDemo)
            ->where(fn (Builder $query) => $query
                ->whereNull($column)
                ->orWhere($column, 'Unknown'))
            ->count();
    }

    private function countryRow(?string $countryCode, ?string $countryName, int $count): array
    {
        return [
            'country_code' => $countryCode,
            'country_name' => $countryName ?: 'Unknown',
            'count' => $count,
        ];
    }

    private function cityRow(?string $cityName, ?string $regionName, ?string $countryCode, ?string $countryName, int $count): array
    {
        return [
            'city_name' => $cityName ?: 'Unknown',
            'region_name' => $regionName ?: 'Unknown',
            'country_code' => $countryCode,
            'country_name' => $countryName ?: 'Unknown',
            'count' => $count,
        ];
    }

    private function hasUsersCountryColumns(string $codeColumn, string $nameColumn): bool
    {
        return Schema::hasTable('users')
            && Schema::hasColumn('users', $codeColumn)
            && Schema::hasColumn('users', $nameColumn);
    }

    private function hasUsersCityColumns(string $cityColumn, string $regionColumn, string $countryCodeColumn, string $countryNameColumn): bool
    {
        return Schema::hasTable('users')
            && Schema::hasColumn('users', $cityColumn)
            && Schema::hasColumn('users', $regionColumn)
            && Schema::hasColumn('users', $countryCodeColumn)
            && Schema::hasColumn('users', $countryNameColumn);
    }

    private function hasAnalyticsEventCityColumns(): bool
    {
        return $this->hasAnalyticsEventsTable()
            && Schema::hasColumn('analytics_events', 'city_name')
            && Schema::hasColumn('analytics_events', 'region_name');
    }

    private function hasAnalyticsEventsTable(): bool
    {
        return Schema::hasTable('analytics_events');
    }

    private function days(Request $request): ?int
    {
        $days = $request->query('days', '30');

        if ($days === 'all') {
            return null;
        }

        return in_array((string) $days, ['7', '30', '90'], true)
            ? (int) $days
            : 30;
    }
}
