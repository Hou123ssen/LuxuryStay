<?php

namespace App\Services\Admin;

use App\Models\AnalyticsEvent;
use App\Models\Booking;
use App\Models\Report;
use App\Models\Review;
use App\Models\User;
use App\Services\Analytics\DemoAnalyticsDataService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class AdminDashboardChartsService
{
    public function __construct(private readonly DemoAnalyticsDataService $demoData)
    {
    }

    public function overview(Request $request, bool $includeDemo = true): array
    {
        $period = $this->period($request, $includeDemo);

        $registrations = $this->registrationsSeries($period, $includeDemo);
        $logins = $this->analyticsSeries(AnalyticsEvent::TYPE_USER_LOGGED_IN, $period, $includeDemo);
        $bookings = $this->modelSeries(Booking::query(), 'bookings', 'created_at', $period);
        $reviews = $this->modelSeries(Review::query(), 'reviews', 'created_at', $period);
        $reports = $this->hasTable('reports')
            ? $this->modelSeries(Report::query(), 'reports', 'created_at', $period)
            : $this->emptySeries($period);

        return [
            'period' => [
                'days' => $period['days'],
                'group_by' => $period['group_by'],
                'start_date' => $period['start']->toDateString(),
                'end_date' => $period['end']->toDateString(),
            ],
            'series' => [
                'registrations' => $registrations,
                'logins' => $logins,
                'bookings' => $bookings,
                'reviews' => $reviews,
                'reports' => $reports,
            ],
            'breakdowns' => [
                'bookings_by_status' => $this->statusBreakdown(Booking::query(), 'bookings', $period),
                'reviews_by_status' => $this->statusBreakdown(Review::query(), 'reviews', $period),
                'reports_by_status' => $this->hasTable('reports')
                    ? $this->statusBreakdown(Report::query(), 'reports', $period)
                    : [],
            ],
            'totals' => [
                'registrations' => $this->sumSeries($registrations),
                'logins' => $this->sumSeries($logins),
                'bookings' => $this->sumSeries($bookings),
                'reviews' => $this->sumSeries($reviews),
                'reports' => $this->sumSeries($reports),
            ],
        ];
    }

    private function period(Request $request, bool $includeDemo): array
    {
        $days = in_array((string) $request->query('days', '30'), ['7', '30', '90', 'all'], true)
            ? (string) $request->query('days', '30')
            : '30';

        $defaultGroupBy = $days === 'all' ? 'month' : 'day';
        $groupBy = in_array((string) $request->query('group_by', $defaultGroupBy), ['day', 'month'], true)
            ? (string) $request->query('group_by', $defaultGroupBy)
            : $defaultGroupBy;

        $end = today();
        $start = $days === 'all'
            ? $this->earliestAvailableDate($includeDemo)->startOfMonth()
            : today()->subDays(((int) $days) - 1);

        return [
            'days' => $days,
            'group_by' => $groupBy,
            'start' => $start->startOfDay(),
            'end' => $end->endOfDay(),
        ];
    }

    private function earliestAvailableDate(bool $includeDemo): Carbon
    {
        $dates = collect([
            $this->minimumTimestamp('users', 'created_at', $includeDemo),
            $this->minimumTimestamp('analytics_events', 'occurred_at', $includeDemo),
            $this->minimumTimestamp('bookings', 'created_at'),
            $this->minimumTimestamp('reviews', 'created_at'),
            $this->minimumTimestamp('reports', 'created_at'),
        ])->filter();

        if ($dates->isEmpty()) {
            return today();
        }

        return $dates
            ->map(fn ($date) => Carbon::parse($date))
            ->sort()
            ->first();
    }

    private function registrationsSeries(array $period, bool $includeDemo): array
    {
        if ($this->hasTable('analytics_events') && $this->hasColumn('analytics_events', 'occurred_at')) {
            $analytics = $this->analyticsSeries(AnalyticsEvent::TYPE_USER_REGISTERED, $period, $includeDemo);

            if ($this->sumSeries($analytics) > 0 || ! $this->hasTable('users')) {
                return $analytics;
            }
        }

        return $this->modelSeries($this->demoData->usersQuery($includeDemo), 'users', 'created_at', $period);
    }

    private function analyticsSeries(string $eventType, array $period, bool $includeDemo): array
    {
        if (! $this->hasTable('analytics_events') || ! $this->hasColumn('analytics_events', 'occurred_at')) {
            return $this->emptySeries($period);
        }

        return $this->modelSeries(
            $this->demoData->eventsQuery($includeDemo)->where('event_type', $eventType),
            'analytics_events',
            'occurred_at',
            $period
        );
    }

    private function modelSeries(Builder $query, string $table, string $dateColumn, array $period): array
    {
        if (! $this->hasTable($table) || ! $this->hasColumn($table, $dateColumn)) {
            return $this->emptySeries($period);
        }

        $counts = $query
            ->whereBetween($dateColumn, [$period['start'], $period['end']])
            ->get([$dateColumn])
            ->map(fn ($row) => $this->bucketLabel($row->{$dateColumn}, $period['group_by']))
            ->countBy();

        return $this->emptySeries($period, $counts);
    }

    private function emptySeries(array $period, ?Collection $counts = null): array
    {
        $counts ??= collect();

        return collect($this->bucketLabels($period))
            ->map(fn (string $label) => [
                'date' => $label,
                'count' => (int) ($counts->get($label) ?? 0),
            ])
            ->values()
            ->all();
    }

    private function bucketLabels(array $period): array
    {
        if ($period['group_by'] === 'month') {
            $labels = [];
            $cursor = $period['start']->copy()->startOfMonth();
            $end = $period['end']->copy()->startOfMonth();

            while ($cursor->lessThanOrEqualTo($end)) {
                $labels[] = $cursor->format('Y-m');
                $cursor->addMonth();
            }

            return $labels;
        }

        return collect(CarbonPeriod::create($period['start']->copy()->startOfDay(), $period['end']->copy()->startOfDay()))
            ->map(fn (CarbonInterface $date) => $date->format('Y-m-d'))
            ->all();
    }

    private function bucketLabel($date, string $groupBy): string
    {
        $date = Carbon::parse($date);

        return $groupBy === 'month'
            ? $date->format('Y-m')
            : $date->format('Y-m-d');
    }

    private function statusBreakdown(Builder $query, string $table, array $period): array
    {
        if (
            ! $this->hasTable($table)
            || ! $this->hasColumn($table, 'status')
            || ! $this->hasColumn($table, 'created_at')
        ) {
            return [];
        }

        return $query
            ->whereBetween('created_at', [$period['start'], $period['end']])
            ->select('status')
            ->selectRaw('COUNT(*) as aggregate')
            ->groupBy('status')
            ->orderBy('status')
            ->get()
            ->map(fn ($row) => [
                'status' => $row->status,
                'count' => (int) $row->aggregate,
            ])
            ->all();
    }

    private function sumSeries(array $series): int
    {
        return collect($series)->sum('count');
    }

    private function minimumTimestamp(string $table, string $column, bool $includeDemo = true): mixed
    {
        if (! $this->hasTable($table) || ! $this->hasColumn($table, $column)) {
            return null;
        }

        return match ($table) {
            'users' => $this->demoData->usersQuery($includeDemo)->min($column),
            'analytics_events' => $this->demoData->eventsQuery($includeDemo)->min($column),
            'bookings' => Booking::query()->min($column),
            'reviews' => Review::query()->min($column),
            'reports' => Report::query()->min($column),
            default => null,
        };
    }

    private function hasTable(string $table): bool
    {
        return Schema::hasTable($table);
    }

    private function hasColumn(string $table, string $column): bool
    {
        return $this->hasTable($table) && Schema::hasColumn($table, $column);
    }
}
