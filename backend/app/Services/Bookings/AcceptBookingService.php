<?php

namespace App\Services\Bookings;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;

class AcceptBookingService
{
    public function __construct(
        private readonly BookingAvailabilityService $availability,
        private readonly BookingNotificationService $notifications,
    ) {}

    public function accept(int $bookingId, User $user): Booking
    {
        $booking = Booking::with('property', 'user')->findOrFail($bookingId);
        $property = $booking->property;

        if ((int) $property->user_id !== (int) $user->id) {
            $this->error(['error' => 'Unauthorized'], 403);
        }

        if ($booking->status !== Booking::STATUS_PENDING) {
            $this->error(['error' => 'Only pending bookings can be accepted.'], 422);
        }

        if ($this->availability->hasAcceptedOverlap(
            $booking->property_id,
            $booking->start_date->toDateString(),
            $booking->end_date->toDateString(),
            $booking->id,
        )) {
            $this->error(['message' => 'These dates are no longer available.'], 409);
        }

        $booking->update(['status' => Booking::STATUS_ACCEPTED]);
        $this->notifications->bookingAccepted($booking);

        return $booking;
    }

    private function error(array $payload, int $status): never
    {
        throw new HttpResponseException(response()->json($payload, $status));
    }
}
