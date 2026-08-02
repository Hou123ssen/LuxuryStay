<?php

namespace App\Services\Admin;

use App\Models\User;
use App\Services\Analytics\DemoAnalyticsDataService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;

class AdminUserQueryService
{
    public function __construct(private readonly DemoAnalyticsDataService $demoData)
    {
    }

    public function paginate(Request $request, bool $includeDemo): LengthAwarePaginator
    {
        $perPage = min(max((int) $request->query('per_page', 15), 1), 50);
        $query = $this->baseQuery($includeDemo);

        $this->applyFilters($query, $request);
        $this->applySort($query, (string) $request->query('sort', 'newest'));

        return $query
            ->paginate($perPage)
            ->appends($request->query());
    }

    public function forDetail(User $user, bool $includeDemo): User
    {
        return $this->withSafeCounts(User::query()->whereKey($user->id), $includeDemo)->firstOrFail();
    }

    private function baseQuery(bool $includeDemo): Builder
    {
        return $this->withSafeCounts($this->demoData->usersQuery($includeDemo), $includeDemo);
    }

    private function withSafeCounts(Builder $query, bool $includeDemo): Builder
    {
        $query->withCount(['properties', 'bookings', 'reviews']);

        if ($this->hasReportsTable()) {
            $query->selectSub(function ($reports) {
                $reports
                    ->from('reports')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('reports.reporter_user_id', 'users.id');
            }, 'reports_count');
        }

        if ($this->hasAnalyticsEventsTable()) {
            $query->selectSub(function ($events) use ($includeDemo) {
                $events
                    ->from('analytics_events')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('analytics_events.user_id', 'users.id');

                if (! $includeDemo) {
                    $events->where(function ($analytics) {
                        $analytics
                            ->whereNull('metadata')
                            ->orWhere('metadata->demo', '!=', true)
                            ->orWhere('metadata->source', '!=', DemoAnalyticsDataService::DEMO_SOURCE);
                    });
                }
            }, 'analytics_events_count');
        }

        return $query;
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $query->where(function (Builder $users) use ($search) {
                $users
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $role = $request->query('role');
        if (in_array($role, ['admin', 'user', 'owner', 'guest'], true)) {
            $query->where('role', $role);
        }

        $countryCode = strtoupper(trim((string) $request->query('country_code', '')));
        if ($countryCode !== '') {
            $query->where(function (Builder $users) use ($countryCode) {
                if ($this->hasUserColumn('registered_country_code')) {
                    $users->orWhere('registered_country_code', $countryCode);
                }

                if ($this->hasUserColumn('last_seen_country_code')) {
                    $users->orWhere('last_seen_country_code', $countryCode);
                }
            });
        }

        $city = trim((string) $request->query('city', ''));
        if ($city !== '') {
            $query->where(function (Builder $users) use ($city) {
                if ($this->hasUserColumn('registered_city_name')) {
                    $users->orWhere('registered_city_name', 'like', "%{$city}%");
                }

                if ($this->hasUserColumn('last_seen_city_name')) {
                    $users->orWhere('last_seen_city_name', 'like', "%{$city}%");
                }
            });
        }

        if ($request->has('has_properties')) {
            $this->booleanFilter($query, 'properties', $request->query('has_properties'));
        }

        if ($request->has('has_bookings')) {
            $this->booleanFilter($query, 'bookings', $request->query('has_bookings'));
        }

        if ($request->has('demo')) {
            $isDemo = filter_var($request->query('demo'), FILTER_VALIDATE_BOOLEAN);
            $query->where('email', $isDemo ? 'like' : 'not like', DemoAnalyticsDataService::DEMO_EMAIL_PATTERN);
        }
    }

    private function booleanFilter(Builder $query, string $relation, mixed $value): void
    {
        $enabled = filter_var($value, FILTER_VALIDATE_BOOLEAN);

        if ($enabled) {
            $query->has($relation);
        } else {
            $query->doesntHave($relation);
        }
    }

    private function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            'oldest' => $query->oldest('created_at'),
            'name' => $query->orderBy('name')->orderBy('id'),
            'last_seen' => $this->hasUserColumn('last_seen_at')
                ? $query->orderByDesc('last_seen_at')->orderByDesc('created_at')
                : $query->latest(),
            'bookings_count' => $query->orderByDesc('bookings_count')->orderByDesc('created_at'),
            'properties_count' => $query->orderByDesc('properties_count')->orderByDesc('created_at'),
            default => $query->latest('created_at'),
        };
    }

    private function hasUserColumn(string $column): bool
    {
        return Schema::hasColumn('users', $column);
    }

    private function hasReportsTable(): bool
    {
        return Schema::hasTable('reports') && Schema::hasColumn('reports', 'reporter_user_id');
    }

    private function hasAnalyticsEventsTable(): bool
    {
        return Schema::hasTable('analytics_events') && Schema::hasColumn('analytics_events', 'user_id');
    }
}
