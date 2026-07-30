<?php

namespace App\Services\Reviews;

use App\Models\Booking;
use App\Models\Property;
use App\Models\Review;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreateReviewService
{
    public function __construct(private readonly ReviewRiskService $risk)
    {
    }

    public function create(User $user, array $data, array $context = []): Review
    {
        try {
            return DB::transaction(function () use ($user, $data, $context) {
                return $this->createInsideTransaction($user, $data, $context);
            });
        } catch (QueryException $exception) {
            if ($this->isUniqueConstraintViolation($exception)) {
                throw $this->duplicateReviewError();
            }

            throw $exception;
        }
    }

    private function createInsideTransaction(User $user, array $data, array $context): Review
    {
        $property = Property::findOrFail($data['property_id']);

        if ((int) $property->user_id === (int) $user->id) {
            throw $this->error('You cannot review your own property.', 403);
        }

        $booking = Booking::where('id', $data['booking_id'])
            ->where('user_id', $user->id)
            ->lockForUpdate()
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

        if (Review::where('booking_id', $booking->id)->lockForUpdate()->exists()) {
            throw $this->duplicateReviewError();
        }

        $risk = $this->risk->assess($user, $property, $booking, $data, $context);
        $isHighRisk = $this->risk->isHighRisk((int) $risk['risk_score']);

        $review = new Review([
            'user_id' => $user->id,
            'property_id' => $property->id,
            'booking_id' => $booking->id,
            'rating' => (int) $data['rating'],
            'comment' => $data['comment'] ?? null,
        ]);

        $review->status = $isHighRisk ? Review::STATUS_PENDING_REVIEW : Review::STATUS_PUBLISHED;
        $review->published_at = $isHighRisk ? null : now();
        $review->risk_score = $risk['risk_score'];
        $review->risk_reasons = $risk['risk_reasons'];
        $review->ip_hash = $risk['ip_hash'];
        $review->user_agent_hash = $risk['user_agent_hash'];
        $review->save();

        return $review->load('user');
    }

    private function error(string $message, int $status): HttpResponseException
    {
        return new HttpResponseException(response()->json([
            'message' => $message,
        ], $status));
    }

    private function duplicateReviewError(): HttpResponseException
    {
        return $this->error('This stay has already been reviewed.', 409);
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? '');

        return in_array($sqlState, ['23000', '23505'], true)
            || str_contains(strtolower($exception->getMessage()), 'unique constraint');
    }
}
