<?php

namespace App\Services\Admin;

use App\Models\AnalyticsEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class AdminGeographyAnalyticsService
{
    public function overview(Request $request): array
    {
        $days = $this->days($request);

        return [
            'summary' => $this->summary($days),
            'users_by_registered_country' => $this->usersByCountry('registered_country_code', 'registered_country_name'),
            'users_by_last_seen_country' => $this->usersByCountry('last_seen_country_code', 'last_seen_country_name'),
            'usage_events_by_country' => $this->eventsByCountry($days),
            'login_events_by_country' => $this->eventsByCountry($days, AnalyticsEvent::TYPE_USER_LOGGED_IN),
            'registration_events_by_country' => $this->eventsByCountry($days, AnalyticsEvent::TYPE_USER_REGISTERED),
            'recent_country_activity' => $this->recentCountryActivity($days),
        ];
    }

    private function summary(?int $days): array
    {
        return [
            'known_registered_country_users_count' => $this->knownUserCountryCount('registered_country_code'),
            'unknown_registered_country_users_count' => $this->unknownUserCountryCount('registered_country_code'),
            'known_last_seen_country_users_count' => $this->knownUserCountryCount('last_seen_country_code'),
            'unknown_last_seen_country_users_count' => $this->unknownUserCountryCount('last_seen_country_code'),
            'usage_events_count' => $this->hasAnalyticsEventsTable()
                ? $this->eventsBaseQuery($days)->count()
                : 0,
        ];
    }

    private function usersByCountry(string $codeColumn, string $nameColumn): array
    {
        if (! $this->hasUsersCountryColumns($codeColumn, $nameColumn)) {
            return [];
        }

        return User::query()
            ->selectRaw("$codeColumn as country_code")
            ->selectRaw("$nameColumn as country_name")
            ->selectRaw('COUNT(*) as aggregate')
            ->groupBy($codeColumn, $nameColumn)
            ->orderByDesc('aggregate')
            ->get()
            ->map(fn ($row) => $this->countryRow($row->country_code, $row->country_name, (int) $row->aggregate))
            ->all();
    }

    private function eventsByCountry(?int $days, ?string $eventType = null): array
    {
        if (! $this->hasAnalyticsEventsTable()) {
            return [];
        }

        $query = $this->eventsBaseQuery($days);

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

    private function recentCountryActivity(?int $days): array
    {
        if (! $this->hasAnalyticsEventsTable()) {
            return [];
        }

        return $this->eventsBaseQuery($days)
            ->latest('occurred_at')
            ->limit(10)
            ->get(['id', 'event_type', 'user_id', 'country_code', 'country_name', 'occurred_at'])
            ->map(fn (AnalyticsEvent $event) => [
                'id' => $event->id,
                'event_type' => $event->event_type,
                'user_id' => $event->user_id,
                'country_code' => $event->country_code,
                'country_name' => $event->country_name ?? 'Unknown',
                'occurred_at' => $event->occurred_at?->toJSON(),
            ])
            ->all();
    }

    private function eventsBaseQuery(?int $days): Builder
    {
        $query = AnalyticsEvent::query();

        if ($days !== null) {
            $query->where('occurred_at', '>=', now()->subDays($days));
        }

        return $query;
    }

    private function knownUserCountryCount(string $codeColumn): int
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', $codeColumn)) {
            return 0;
        }

        return User::query()->whereNotNull($codeColumn)->count();
    }

    private function unknownUserCountryCount(string $codeColumn): int
    {
        if (! Schema::hasTable('users')) {
            return 0;
        }

        if (! Schema::hasColumn('users', $codeColumn)) {
            return User::count();
        }

        return User::query()->whereNull($codeColumn)->count();
    }

    private function countryRow(?string $countryCode, ?string $countryName, int $count): array
    {
        return [
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
