<?php

namespace App\Services\Reviews;

use App\Models\Booking;
use App\Models\Property;
use App\Models\Review;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreateReviewService
{
    public function create(User $user, array $data): Review
    {
        $property = Property::findOrFail($data['property_id']);

        if ((int) $property->user_id === (int) $user->id) {
            throw $this->error('You cannot review your own property.', 403);
        }

        $booking = Booking::where('id', $data['booking_id'])
            ->where('user_id', $user->id)
            ->first();

        if (! $booking) {
            throw $this->error('This booking is not eligible for review.', 403);
        }

        if ((int) $booking->property_id !== (int) $property->id) {
            throw $this->error('This booking does not belong to the selected property.', 422);
        }

        if (! in_array($booking->status, ['accepted', 'completed'], true)) {
            throw $this->error('Only completed accepted stays can be reviewed.', 422);
        }

        if (! $booking->end_date || Carbon::parse($booking->end_date)->startOfDay()->greaterThanOrEqualTo(today())) {
            throw $this->error('Stay is not completed yet.', 422);
        }

        if (Review::where('booking_id', $booking->id)->exists()) {
            throw $this->error('Review already submitted for this booking.', 409);
        }

        return Review::create([
            'user_id' => $user->id,
            'property_id' => $property->id,
            'booking_id' => $booking->id,
            'rating' => (int) $data['rating'],
            'comment' => $data['comment'] ?? null,
        ])->load('user');
    }

    private function error(string $message, int $status): HttpResponseException
    {
        return new HttpResponseException(response()->json([
            'message' => $message,
        ], $status));
    }
}
