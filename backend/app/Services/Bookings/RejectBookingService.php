<?php

namespace App\Services\Bookings;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;

class RejectBookingService
{
    public function __construct(
        private readonly BookingNotificationService $notifications,
    ) {}

    public function reject(int $bookingId, User $user): Booking
    {
        $booking = Booking::with('property', 'user')->findOrFail($bookingId);
        $property = $booking->property;

        if ((int) $property->user_id !== (int) $user->id) {
            $this->error(['error' => 'Unauthorized'], 403);
        }

        if ($booking->status !== Booking::STATUS_PENDING) {
            $this->error(['error' => 'Only pending bookings can be rejected.'], 422);
        }

        $booking->update(['status' => Booking::STATUS_REJECTED]);
        $this->notifications->bookingRejected($booking);

        return $booking;
    }

    private function error(array $payload, int $status): never
    {
        throw new HttpResponseException(response()->json($payload, $status));
    }
}
