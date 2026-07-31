<?php

namespace App\Services\Bookings;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;

class CancelBookingService
{
    public function __construct(
        private readonly BookingNotificationService $notifications,
    ) {}

    public function cancel(int $bookingId, User $user, ?string $reason = null): Booking
    {
        $booking = Booking::with(['property', 'user'])->findOrFail($bookingId);

        $isGuest = (int) $booking->user_id === (int) $user->id;
        $isOwner = (int) $booking->property->user_id === (int) $user->id;

        if (! $isGuest && ! $isOwner) {
            $this->error(['message' => 'You are not allowed to cancel this booking.'], 403);
        }

        if ($booking->status === Booking::STATUS_CANCELLED) {
            $this->error(['message' => 'This booking has already been cancelled.'], 409);
        }

        if ($booking->hasStayStarted()) {
            $this->error(['message' => 'This stay has already started and cannot be cancelled.'], 409);
        }

        $actor = null;
        if ($isGuest && $booking->canBeCancelledByGuest($user)) {
            $actor = Booking::CANCELLATION_ACTOR_GUEST;
        } elseif ($isOwner && $booking->canBeCancelledByOwner($user)) {
            $actor = Booking::CANCELLATION_ACTOR_OWNER;
        }

        if (! $actor) {
            $this->error(['message' => 'This booking can no longer be cancelled.'], 409);
        }

        $reason = trim((string) $reason);
        $booking->cancelBy($user, $actor, $reason !== '' ? $reason : null);
        $booking->refresh()->load(['property.images', 'user', 'cancelledBy']);

        $this->notifications->bookingCancelled($booking, $user);

        return $booking;
    }

    private function error(array $payload, int $status): never
    {
        throw new HttpResponseException(response()->json($payload, $status));
    }
}
