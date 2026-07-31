<?php

namespace App\Services\Reviews;

use App\Models\Review;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class ReviewQueryService
{
    public function paginate(Request $request, int $perPage): LengthAwarePaginator
    {
        return $this->query($request)
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(int $reviewId): Review
    {
        return Review::with(['user', 'property', 'booking'])->findOrFail($reviewId);
    }

    private function query(Request $request)
    {
        return Review::with(['user', 'property', 'booking'])
            ->when($request->query('status'), function ($query, string $status) {
                $query->where('status', $status);
            })
            ->when($request->query('rating'), function ($query, string $rating) {
                $query->where('rating', (int) $rating);
            })
            ->when($request->query('property_id'), function ($query, string $propertyId) {
                $query->where('property_id', (int) $propertyId);
            })
            ->when($request->query('user_id'), function ($query, string $userId) {
                $query->where('user_id', (int) $userId);
            })
            ->when($request->query('risk_level') === 'high', function ($query) {
                $query->where('risk_score', '>=', config('reviews.risk.high_risk_threshold'));
            });
    }
}
