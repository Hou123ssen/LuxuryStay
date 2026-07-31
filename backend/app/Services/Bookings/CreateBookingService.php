<?php

namespace App\Services\Bookings;

use App\Models\Booking;
use App\Models\Property;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreateBookingService
{
    public function __construct(
        private readonly BookingAvailabilityService $availability,
        private readonly BookingNotificationService $notifications,
    ) {}

    public function create(User $user, array $data): Booking
    {
        $property = Property::findOrFail($data['property_id']);

        if ((int) $property->user_id === (int) $user->id) {
            $this->error(['message' => 'You cannot book your own property.'], 403);
        }

        if ($this->availability->hasAcceptedOverlap($property->id, $data['start_date'], $data['end_date'])) {
            $this->error(['message' => 'Property is already booked for these dates.'], 422);
        }

        $nights = Carbon::parse($data['start_date'])->diffInDays(Carbon::parse($data['end_date']));

        if ($nights < 1) {
            $this->error(['message' => 'The booking must be at least one night.'], 422);
        }

        $booking = Booking::create([
            'user_id' => $user->id,
            'property_id' => $property->id,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'total_price' => $nights * $property->price_per_night,
            'status' => Booking::STATUS_PENDING,
        ]);

        $booking->load('property');

        if ((int) $property->user_id !== (int) $user->id) {
            $this->notifications->bookingRequested($booking, $user);
        }

        return $booking;
    }

    private function error(array $payload, int $status): never
    {
        throw new HttpResponseException(response()->json($payload, $status));
    }
}
