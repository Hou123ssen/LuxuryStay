<?php

namespace App\Services\Admin;

use App\Models\Booking;
use App\Models\Property;
use App\Models\Report;
use App\Models\Review;
use App\Models\User;
use App\Support\OwnerReliability;
use App\Support\PropertyRating;
use Illuminate\Support\Facades\Schema;

class AdminDashboardOverviewService
{
    public function overview(): array
    {
        $moderation = $this->moderation();
        $trustAndSafety = $this->trustAndSafety();

        return [
            'totals' => $this->totals(),
            'bookings' => $this->bookings(),
            'moderation' => $moderation,
            'trust_and_safety' => $trustAndSafety,
            'recent_activity' => $this->recentActivity(),
            'alerts' => $this->alerts($moderation, $trustAndSafety),
        ];
    }

    private function totals(): array
    {
        $roleCounts = User::query()
            ->select('role')
            ->selectRaw('COUNT(*) as aggregate')
            ->groupBy('role')
            ->pluck('aggregate', 'role')
            ->map(fn ($count) => (int) $count);

        return [
            'users_count' => User::count(),
            'guests_count' => (int) ($roleCounts->get('guest') ?? 0),
            'owners_count' => (int) ($roleCounts->get('owner') ?? Property::query()->distinct('user_id')->count('user_id')),
            'admins_count' => (int) ($roleCounts->get('admin') ?? 0),
            'role_breakdown' => $roleCounts->all(),
            'properties_count' => $this->countIfTableExists('properties'),
            'bookings_count' => $this->countIfTableExists('bookings'),
            'reviews_count' => $this->countIfTableExists('reviews'),
            'reports_count' => $this->countIfTableExists('reports'),
        ];
    }

    private function bookings(): array
    {
        if (! $this->hasTable('bookings')) {
            return [
                'pending_bookings_count' => 0,
                'accepted_bookings_count' => 0,
                'completed_bookings_count' => 0,
                'cancelled_bookings_count' => 0,
                'rejected_bookings_count' => 0,
                'owner_cancelled_bookings_count' => 0,
                'guest_cancelled_bookings_count' => 0,
            ];
        }

        return [
            'pending_bookings_count' => Booking::where('status', Booking::STATUS_PENDING)->count(),
            'accepted_bookings_count' => Booking::where('status', Booking::STATUS_ACCEPTED)->count(),
            'completed_bookings_count' => Booking::where('status', Booking::STATUS_COMPLETED)->count(),
            'cancelled_bookings_count' => Booking::where('status', Booking::STATUS_CANCELLED)->count(),
            'rejected_bookings_count' => Booking::where('status', Booking::STATUS_REJECTED)->count(),
            'owner_cancelled_bookings_count' => $this->hasColumn('bookings', 'cancellation_actor')
                ? Booking::where('cancellation_actor', Booking::CANCELLATION_ACTOR_OWNER)->count()
                : 0,
            'guest_cancelled_bookings_count' => $this->hasColumn('bookings', 'cancellation_actor')
                ? Booking::where('cancellation_actor', Booking::CANCELLATION_ACTOR_GUEST)->count()
                : 0,
        ];
    }

    private function moderation(): array
    {
        $highRiskThreshold = config('reviews.risk.high_risk_threshold');
        $hasReports = $this->hasTable('reports') && $this->hasColumn('reports', 'status');
        $hasReviews = $this->hasTable('reviews') && $this->hasColumn('reviews', 'status');

        return [
            'pending_reports_count' => $hasReports ? Report::where('status', Report::STATUS_PENDING)->count() : 0,
            'reviewed_reports_count' => $hasReports ? Report::where('status', Report::STATUS_REVIEWED)->count() : 0,
            'unresolved_reports_count' => $hasReports ? Report::open()->count() : 0,
            'pending_reviews_count' => $hasReviews ? Review::where('status', Review::STATUS_PENDING_REVIEW)->count() : 0,
            'rejected_reviews_count' => $hasReviews ? Review::where('status', Review::STATUS_REJECTED)->count() : 0,
            'published_reviews_count' => $hasReviews ? Review::where('status', Review::STATUS_PUBLISHED)->count() : 0,
            'high_risk_reviews_count' => $hasReviews && $this->hasColumn('reviews', 'risk_score')
                ? Review::where('status', Review::STATUS_PENDING_REVIEW)
                    ->where('risk_score', '>=', $highRiskThreshold)
                    ->count()
                : 0,
        ];
    }

    private function trustAndSafety(): array
    {
        return [
            'properties_with_trust_badge_count' => $this->propertiesWithTrustBadgeCount(),
            'properties_with_unresolved_reports_count' => $this->propertiesWithUnresolvedReportsCount(),
            'properties_with_serious_report_signals_count' => $this->propertiesWithSeriousReportSignalsCount(),
            'owners_with_high_cancellation_rate_count' => $this->ownersWithHighCancellationRateCount(),
        ];
    }

    private function propertiesWithTrustBadgeCount(): int
    {
        if (! $this->hasTable('properties') || ! $this->hasTable('reviews') || ! $this->hasColumn('reviews', 'status')) {
            return 0;
        }

        $highRiskThreshold = config('reviews.risk.high_risk_threshold');
        $query = Property::query()
            ->withAvg(['reviews' => fn ($query) => $query->published()], 'rating')
            ->withCount(['reviews' => fn ($query) => $query->published()]);

        if ($this->hasColumn('reviews', 'risk_score')) {
            $query->withCount([
                'reviews as pending_high_risk_reviews_count' => fn ($query) => $query
                    ->where('status', Review::STATUS_PENDING_REVIEW)
                    ->where('risk_score', '>=', $highRiskThreshold),
            ]);
        }

        $properties = $query->get();

        return $properties
            ->map(fn (Property $property) => PropertyRating::apply($property))
            ->filter(fn (Property $property) => $property->trust_badge !== null)
            ->count();
    }

    private function propertiesWithSeriousReportSignalsCount(): int
    {
        if (
            ! $this->hasTable('reports')
            || ! $this->hasColumn('reports', 'status')
            || ! $this->hasColumn('reports', 'severity')
            || ! $this->hasColumn('reports', 'category')
            || ! $this->hasColumn('reports', 'property_id')
        ) {
            return 0;
        }

        return Report::query()
            ->whereIn('status', Report::OPEN_STATUSES)
            ->where(function ($signals) {
                $signals
                    ->whereIn('severity', [Report::SEVERITY_HIGH, Report::SEVERITY_CRITICAL])
                    ->orWhereIn('category', [Report::CATEGORY_UNSAFE_PROPERTY, Report::CATEGORY_SCAM_OR_FRAUD]);
            })
            ->distinct('property_id')
            ->count('property_id');
    }

    private function propertiesWithUnresolvedReportsCount(): int
    {
        if (
            ! $this->hasTable('reports')
            || ! $this->hasColumn('reports', 'status')
            || ! $this->hasColumn('reports', 'property_id')
        ) {
            return 0;
        }

        return Report::open()->distinct('property_id')->count('property_id');
    }

    private function ownersWithHighCancellationRateCount(): int
    {
        if (
            ! $this->hasTable('properties')
            || ! $this->hasTable('bookings')
            || ! $this->hasColumn('bookings', 'cancellation_actor')
        ) {
            return 0;
        }

        return OwnerReliability::forOwnerIds(Property::query()->pluck('user_id'))
            ->filter(fn (array $metrics) => $metrics['owner_reliability_label'] === 'High cancellation history')
            ->count();
    }

    private function recentActivity(): array
    {
        return [
            'bookings' => $this->hasTable('bookings') ? Booking::query()
                ->with('property:id,title')
                ->latest()
                ->limit(5)
                ->get()
                ->map(fn (Booking $booking) => [
                    'id' => $booking->id,
                    'status' => $booking->status,
                    'property_id' => $booking->property_id,
                    'property_title' => $booking->property?->title,
                    'user_id' => $booking->user_id,
                    'start_date' => $booking->start_date?->toDateString(),
                    'end_date' => $booking->end_date?->toDateString(),
                    'created_at' => $booking->created_at?->toJSON(),
                ])
                ->all() : [],
            'reports' => $this->hasTable('reports') ? Report::query()
                ->with('property:id,title')
                ->latest()
                ->limit(5)
                ->get()
                ->map(fn (Report $report) => [
                    'id' => $report->id,
                    'status' => $report->status,
                    'category' => $report->category,
                    'severity' => $report->severity,
                    'property_id' => $report->property_id,
                    'property_title' => $report->property?->title,
                    'created_at' => $report->created_at?->toJSON(),
                ])
                ->all() : [],
            'reviews' => $this->hasTable('reviews') ? Review::query()
                ->with('property:id,title')
                ->latest()
                ->limit(5)
                ->get()
                ->map(fn (Review $review) => [
                    'id' => $review->id,
                    'status' => $review->status,
                    'rating' => $review->rating,
                    'property_id' => $review->property_id,
                    'property_title' => $review->property?->title,
                    'user_id' => $review->user_id,
                    'created_at' => $review->created_at?->toJSON(),
                ])
                ->all() : [],
        ];
    }

    private function alerts(array $moderation, array $trustAndSafety): array
    {
        $alerts = [];

        if ($this->hasIncompleteDashboardData()) {
            $alerts[] = $this->alert(
                'dashboard_data_incomplete',
                'warning',
                'Dashboard data incomplete',
                'Some platform data cannot be calculated because database setup is incomplete.',
                null
            );
        }

        if ($moderation['pending_reports_count'] > 0) {
            $alerts[] = $this->alert('pending_reports', 'warning', 'Reports need review', 'There are pending guest reports waiting for moderation.', '/admin/reports');
        }

        if ($moderation['pending_reviews_count'] > 0) {
            $alerts[] = $this->alert('pending_reviews', 'warning', 'Reviews need moderation', 'There are reviews waiting for moderation.', '/admin/reviews');
        }

        if ($moderation['high_risk_reviews_count'] > 0) {
            $alerts[] = $this->alert('high_risk_reviews', 'critical', 'High-risk reviews pending', 'High-risk pending reviews need admin attention.', '/admin/reviews');
        }

        if ($trustAndSafety['properties_with_serious_report_signals_count'] > 0) {
            $alerts[] = $this->alert('serious_report_signals', 'critical', 'Serious property reports', 'Some properties have unresolved serious report signals.', '/admin/reports');
        }

        if ($trustAndSafety['owners_with_high_cancellation_rate_count'] > 0) {
            $alerts[] = $this->alert('owner_cancellation_reliability', 'warning', 'Host cancellation reliability', 'Some established hosts have a high cancellation history.', '/admin/reports');
        }

        return $alerts;
    }

    private function alert(string $type, string $severity, string $title, string $description, ?string $actionUrl): array
    {
        return [
            'type' => $type,
            'severity' => $severity,
            'title' => $title,
            'description' => $description,
            'action_url' => $actionUrl,
        ];
    }

    private function hasIncompleteDashboardData(): bool
    {
        return collect(['users', 'properties', 'bookings', 'reviews', 'reports'])
            ->contains(fn (string $table) => ! $this->hasTable($table));
    }

    private function countIfTableExists(string $table): int
    {
        if (! $this->hasTable($table)) {
            return 0;
        }

        return match ($table) {
            'bookings' => Booking::count(),
            'properties' => Property::count(),
            'reports' => Report::count(),
            'reviews' => Review::count(),
            default => 0,
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
