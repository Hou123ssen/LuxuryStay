<?php

namespace App\Http\Resources;

use App\Models\Booking;
use App\Models\Image;
use App\Models\Report;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class AdminPropertyDetailResource extends AdminPropertyResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'recent_bookings' => $this->recentBookings(),
            'recent_reviews' => $this->recentReviews(),
            'recent_reports' => $this->recentReports(),
            'images' => $this->images(),
        ]);
    }

    private function recentBookings(): array
    {
        if (! Schema::hasTable('bookings')) {
            return [];
        }

        return Booking::query()
            ->where('property_id', $this->id)
            ->with('user:id,name')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (Booking $booking) => [
                'id' => $booking->id,
                'status' => $booking->status,
                'user_id' => $booking->user_id,
                'guest_name' => $booking->user?->name,
                'start_date' => $booking->start_date?->toDateString(),
                'end_date' => $booking->end_date?->toDateString(),
                'total_price' => $booking->total_price === null ? null : (float) $booking->total_price,
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
            ->where('property_id', $this->id)
            ->with('user:id,name')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (Review $review) => [
                'id' => $review->id,
                'user_id' => $review->user_id,
                'reviewer_name' => $review->user?->name,
                'rating' => $review->rating,
                'status' => $review->status,
                'created_at' => $review->created_at?->toJSON(),
            ])
            ->all();
    }

    private function recentReports(): array
    {
        if (! Schema::hasTable('reports') || ! Schema::hasColumn('reports', 'property_id')) {
            return [];
        }

        return Report::query()
            ->where('property_id', $this->id)
            ->with('reporter:id,name')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (Report $report) => [
                'id' => $report->id,
                'status' => $report->status,
                'category' => $report->category,
                'severity' => $report->severity,
                'created_at' => $report->created_at?->toJSON(),
                'reporter_id' => $report->reporter_user_id,
                'reporter_name' => $report->reporter?->name,
            ])
            ->all();
    }

    private function images(): array
    {
        if (! Schema::hasTable('images') || ! Schema::hasColumn('images', 'property_id')) {
            return [];
        }

        return Image::query()
            ->where('property_id', $this->id)
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (Image $image) => [
                'id' => $image->id,
                'url' => $image->url,
                'is_primary' => $image->getAttribute('is_primary'),
                'created_at' => $image->created_at?->toJSON(),
            ])
            ->all();
    }
}
