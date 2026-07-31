<?php

namespace App\Services\Bookings;

use App\Models\Booking;
use App\Models\Property;
use Carbon\Carbon;

class BookingAvailabilityService
{
    public function hasAcceptedOverlap(int $propertyId, string $startDate, string $endDate, ?int $exceptBookingId = null): bool
    {
        return Booking::where('property_id', $propertyId)
            ->when($exceptBookingId, fn ($query) => $query->where('id', '!=', $exceptBookingId))
            ->where('status', Booking::STATUS_ACCEPTED)
            ->whereDate('start_date', '<', $endDate)
            ->whereDate('end_date', '>', $startDate)
            ->exists();
    }

    public function unavailableForProperty(Property $property): array
    {
        $bookings = $property->bookings()
            ->where('status', Booking::STATUS_ACCEPTED)
            ->orderBy('start_date')
            ->get(['start_date', 'end_date']);

        $unavailableDates = [];

        $unavailableRanges = $bookings->map(function ($booking) use (&$unavailableDates) {
            $start = Carbon::parse($booking->start_date)->startOfDay();
            $end = Carbon::parse($booking->end_date)->startOfDay();

            for ($date = $start->copy(); $date->lt($end); $date->addDay()) {
                $unavailableDates[] = $date->toDateString();
            }

            return [
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
            ];
        })->values();

        $unavailableDates = array_values(array_unique($unavailableDates));
        sort($unavailableDates);

        return [
            'unavailable_ranges' => $unavailableRanges,
            'unavailable_dates' => $unavailableDates,
        ];
    }
}
