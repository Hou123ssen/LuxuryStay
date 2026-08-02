<?php

namespace App\Http\Resources;

use App\Models\AnalyticsEvent;
use App\Models\Booking;
use App\Models\Report;
use App\Models\Review;
use App\Services\Analytics\DemoAnalyticsDataService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class AdminUserDetailResource extends AdminUserResource
{
    public function toArray(Request $request): array
    {
        $includeDemo = app(DemoAnalyticsDataService::class)->includeDemo($request);

        return array_merge(parent::toArray($request), [
            'recent_bookings' => $this->recentBookings(),
            'recent_reviews' => $this->recentReviews(),
            'recent_reports' => $this->recentReports(),
            'recent_analytics_activity' => $this->recentAnalyticsActivity($includeDemo),
        ]);
    }

    private function recentBookings(): array
    {
        if (! Schema::hasTable('bookings')) {
            return [];
        }

        return Booking::query()
            ->where('user_id', $this->id)
            ->with('property:id,title')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (Booking $booking) => [
                'id' => $booking->id,
                'status' => $booking->status,
                'property_id' => $booking->property_id,
                'property_title' => $booking->property?->title,
                'start_date' => $booking->start_date?->toDateString(),
                'end_date' => $booking->end_date?->toDateString(),
                'created_at' => $booking->created_at?->toJSON(),
            ])
            ->all();
    }

    private function recentReviews(): array
    {
        if (! Schema::hasTable('reviews')) {
            return [];
        }

        return Review::query()
            ->where('user_id', $this->id)
            ->with('property:id,title')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (Review $review) => [
                'id' => $review->id,
                'property_id' => $review->property_id,
                'property_title' => $review->property?->title,
                'rating' => $review->rating,
                'status' => $review->status,
                'created_at' => $review->created_at?->toJSON(),
            ])
            ->all();
    }

    private function recentReports(): array
    {
        if (! Schema::hasTable('reports') || ! Schema::hasColumn('reports', 'reporter_user_id')) {
            return [];
        }

        return Report::query()
            ->where('reporter_user_id', $this->id)
            ->with('property:id,title')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (Report $report) => [
                'id' => $report->id,
                'property_id' => $report->property_id,
                'property_title' => $report->property?->title,
                'status' => $report->status,
                'category' => $report->category,
                'severity' => $report->severity,
                'created_at' => $report->created_at?->toJSON(),
            ])
            ->all();
    }

    private function recentAnalyticsActivity(bool $includeDemo): array
    {
        if (! Schema::hasTable('analytics_events') || ! Schema::hasColumn('analytics_events', 'user_id')) {
            return [];
        }

        $query = app(DemoAnalyticsDataService::class)
            ->eventsQuery($includeDemo)
            ->where('user_id', $this->id)
            ->latest('occurred_at')
            ->limit(10);

        $columns = ['id', 'event_type', 'country_code', 'country_name', 'occurred_at'];

        if (Schema::hasColumn('analytics_events', 'region_name')) {
            $columns[] = 'region_name';
        }

        if (Schema::hasColumn('analytics_events', 'city_name')) {
            $columns[] = 'city_name';
        }

        return $query
            ->get($columns)
            ->map(fn (AnalyticsEvent $event) => [
                'id' => $event->id,
                'event_type' => $event->event_type,
                'country_code' => $event->country_code,
                'country_name' => $event->country_name,
                'region_name' => $event->getAttribute('region_name'),
                'city_name' => $event->getAttribute('city_name'),
                'occurred_at' => $event->occurred_at?->toJSON(),
            ])
            ->all();
    }
}
