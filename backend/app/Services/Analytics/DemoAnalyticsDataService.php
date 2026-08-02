<?php

namespace App\Services\Analytics;

use App\Models\AnalyticsEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class DemoAnalyticsDataService
{
    public const DEMO_EMAIL_PATTERN = 'geo.demo.%@luxurrstay.test';
    public const DEMO_SOURCE = 'local_geography_demo';

    public function includeDemo(Request $request): bool
    {
        $value = $request->query('include_demo');

        if ($value !== null) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        if (config('app.env') === 'production') {
            return false;
        }

        return app()->environment(['local', 'testing']) || config('app.env') === 'local';
    }

    public function meta(bool $included): array
    {
        $counts = $this->counts();
        $available = $counts['demo_users_count'] > 0 || $counts['demo_events_count'] > 0;

        return [
            'included' => $included && $available,
            'available' => $available,
            'demo_users_count' => $counts['demo_users_count'],
            'demo_events_count' => $counts['demo_events_count'],
            'demo_registration_events_count' => $counts['demo_registration_events_count'],
            'demo_login_events_count' => $counts['demo_login_events_count'],
            'message' => $included && $available
                ? 'Local demo analytics data is included in this view.'
                : ($available ? 'Local demo analytics data is available but excluded from this view.' : null),
        ];
    }

    public function counts(): array
    {
        $eventsQuery = $this->demoEventsQuery();

        return [
            'demo_users_count' => $this->hasUsersTable()
                ? $this->demoUsersQuery()->count()
                : 0,
            'demo_events_count' => $eventsQuery ? (clone $eventsQuery)->count() : 0,
            'demo_registration_events_count' => $eventsQuery
                ? (clone $eventsQuery)->where('event_type', AnalyticsEvent::TYPE_USER_REGISTERED)->count()
                : 0,
            'demo_login_events_count' => $eventsQuery
                ? (clone $eventsQuery)->where('event_type', AnalyticsEvent::TYPE_USER_LOGGED_IN)->count()
                : 0,
        ];
    }

    public function usersQuery(bool $includeDemo): Builder
    {
        $query = User::query();

        return $includeDemo ? $query : $this->excludeDemoUsers($query);
    }

    public function eventsQuery(bool $includeDemo): Builder
    {
        $query = AnalyticsEvent::query();

        return $includeDemo ? $query : $this->excludeDemoEvents($query);
    }

    public function excludeDemoUsers(Builder $query): Builder
    {
        return $query->where('email', 'not like', self::DEMO_EMAIL_PATTERN);
    }

    public function excludeDemoEvents(Builder $query): Builder
    {
        return $query->where(function (Builder $events) {
            $events
                ->whereNull('metadata')
                ->orWhere('metadata->demo', '!=', true)
                ->orWhere('metadata->source', '!=', self::DEMO_SOURCE);
        });
    }

    public function deleteDemoData(): array
    {
        $events = $this->demoEventsQuery()?->delete() ?? 0;
        $users = $this->hasUsersTable() ? $this->demoUsersQuery()->delete() : 0;

        return ['events' => $events, 'users' => $users];
    }

    private function demoUsersQuery(): Builder
    {
        return User::query()->where('email', 'like', self::DEMO_EMAIL_PATTERN);
    }

    private function demoEventsQuery(): ?Builder
    {
        if (! $this->hasAnalyticsEventsTable()) {
            return null;
        }

        return AnalyticsEvent::query()
            ->where('metadata->demo', true)
            ->where('metadata->source', self::DEMO_SOURCE);
    }

    private function hasUsersTable(): bool
    {
        return Schema::hasTable('users');
    }

    private function hasAnalyticsEventsTable(): bool
    {
        return Schema::hasTable('analytics_events')
            && Schema::hasColumn('analytics_events', 'metadata');
    }
}
