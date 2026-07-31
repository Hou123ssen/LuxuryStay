<?php

namespace App\Services\Bookings;

use App\Models\Booking;
use App\Models\Notification;
use App\Models\User;

class BookingNotificationService
{
    public function bookingRequested(Booking $booking, User $booker): void
    {
        $property = $booking->property;

        Notification::create([
            'user_id' => $property->user_id,
            'message' => json_encode([
                'type' => 'booking_request',
                'booking_id' => $booking->id,
                'property_id' => $property->id,
                'booker_id' => $booker->id,
                'booker_name' => $booker->name,
                'property' => $property->title,
                'property_title' => $property->title,
                'guest_name' => $booker->name,
                'check_in' => $booking->start_date->toDateString(),
                'check_out' => $booking->end_date->toDateString(),
                'start_date' => $booking->start_date->toDateString(),
                'end_date' => $booking->end_date->toDateString(),
                'text' => "{$booker->name} requested to book \"{$property->title}\" from {$booking->start_date->toDateString()} to {$booking->end_date->toDateString()}.",
            ]),
        ]);

        Notification::create([
            'user_id' => $booker->id,
            'message' => json_encode([
                'type' => 'booking_pending',
                'booking_id' => $booking->id,
                'property_id' => $property->id,
                'text' => "Your booking request for \"{$property->title}\" from {$booking->start_date->toDateString()} to {$booking->end_date->toDateString()} is awaiting owner approval.",
            ]),
        ]);
    }

    public function bookingAccepted(Booking $booking): void
    {
        $property = $booking->property;

        Notification::create([
            'user_id' => $booking->user_id,
            'message' => json_encode([
                'type' => 'booking_accepted',
                'booking_id' => $booking->id,
                'property_id' => $property->id,
                'text' => "✅ Your booking for \"{$property->title}\" from {$booking->start_date} to {$booking->end_date} has been accepted!",
            ]),
        ]);
    }

    public function bookingRejected(Booking $booking): void
    {
        $property = $booking->property;

        Notification::create([
            'user_id' => $booking->user_id,
            'message' => json_encode([
                'type' => 'booking_rejected',
                'booking_id' => $booking->id,
                'property_id' => $property->id,
                'text' => "❌ Your booking request for \"{$property->title}\" from {$booking->start_date} to {$booking->end_date} was declined.",
            ]),
        ]);
    }

    public function bookingCancelled(Booking $booking, User $actor): void
    {
        $property = $booking->property;
        $cancelledAt = optional($booking->cancelled_at)->toIso8601String();
        $reason = $booking->cancellation_reason;

        if ($booking->cancellation_actor === Booking::CANCELLATION_ACTOR_GUEST) {
            Notification::create([
                'user_id' => $property->user_id,
                'message' => json_encode([
                    'type' => 'booking_cancelled_by_guest',
                    'booking_id' => $booking->id,
                    'property_id' => $property->id,
                    'property_title' => $property->title,
                    'guest_name' => $booking->user?->name,
                    'cancelled_by' => Booking::CANCELLATION_ACTOR_GUEST,
                    'cancellation_reason' => $reason,
                    'cancelled_at' => $cancelledAt,
                    'text' => "{$booking->user?->name} cancelled their booking for \"{$property->title}\".",
                ]),
            ]);

            return;
        }

        Notification::create([
            'user_id' => $booking->user_id,
            'message' => json_encode([
                'type' => 'booking_cancelled_by_owner',
                'booking_id' => $booking->id,
                'property_id' => $property->id,
                'property_title' => $property->title,
                'owner_name' => $actor->name,
                'cancelled_by' => Booking::CANCELLATION_ACTOR_OWNER,
                'cancellation_reason' => $reason,
                'cancelled_at' => $cancelledAt,
                'text' => "Your booking for \"{$property->title}\" was cancelled by the owner.",
            ]),
        ]);
    }
}
