<?php

namespace App\Services\Properties;

use App\Models\Booking;
use App\Models\Property;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ReviewEligibilityService
{
    public function eligibleBookingsFor(Property $property, ?int $userId): Collection
    {
        if (! $userId || (int) $property->user_id === $userId) {
            return collect();
        }

        return Booking::where('user_id', $userId)
            ->where('property_id', $property->id)
            ->whereIn('status', ['accepted', 'completed'])
            ->whereDate('end_date', '<', today())
            ->whereDoesntHave('review')
            ->latest('end_date')
            ->get(['id', 'start_date', 'end_date'])
            ->map(function ($booking) {
                return [
                    'id' => $booking->id,
                    'start_date' => Carbon::parse($booking->start_date)->toDateString(),
                    'end_date' => Carbon::parse($booking->end_date)->toDateString(),
                ];
            })
            ->values();
    }
}
