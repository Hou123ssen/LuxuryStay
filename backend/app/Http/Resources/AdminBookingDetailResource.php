<?php

namespace App\Http\Resources;

use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class AdminBookingDetailResource extends AdminBookingResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'review' => $this->reviewSummary(),
            'reports' => $this->reportsSummary(),
            'payment' => null,
        ]);
    }

    private function reviewSummary(): ?array
    {
        if (! Schema::hasTable('reviews') || ! Schema::hasColumn('reviews', 'booking_id')) {
            return null;
        }

        $review = $this->review()
            ->select(['id', 'booking_id', 'rating', 'status', 'created_at'])
            ->latest()
            ->first();

        if (! $review) {
            return null;
        }

        return [
            'id' => $review->id,
            'rating' => $review->rating,
            'status' => $review->status,
            'created_at' => $review->created_at?->toJSON(),
        ];
    }

    private function reportsSummary(): array
    {
        if (! Schema::hasTable('reports') || ! Schema::hasColumn('reports', 'booking_id')) {
            return [];
        }

        return Report::query()
            ->where('booking_id', $this->id)
            ->latest()
            ->limit(5)
            ->get(['id', 'booking_id', 'status', 'category', 'severity', 'created_at'])
            ->map(fn (Report $report) => [
                'id' => $report->id,
                'status' => $report->status,
                'category' => $report->category,
                'severity' => $report->severity,
                'created_at' => $report->created_at?->toJSON(),
            ])
            ->all();
    }
}
