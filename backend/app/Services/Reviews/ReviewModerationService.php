<?php

namespace App\Services\Reviews;

use App\Models\Review;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

class ReviewModerationService
{
    public function __construct(private readonly ReviewModerationLogService $logs)
    {
    }

    public function publish(Review $review, User $admin, ?string $reason = null): Review
    {
        return $this->transition($review, $admin, Review::STATUS_PUBLISHED, $reason);
    }

    public function reject(Review $review, User $admin, ?string $reason = null): Review
    {
        return $this->transition($review, $admin, Review::STATUS_REJECTED, $reason);
    }

    private function transition(Review $review, User $admin, string $newStatus, ?string $reason): Review
    {
        return DB::transaction(function () use ($review, $admin, $newStatus, $reason) {
            $lockedReview = Review::query()->lockForUpdate()->findOrFail($review->id);
            $oldStatus = $lockedReview->status;

            $this->ensureAllowed($oldStatus, $newStatus);

            $attributes = [
                'status' => $newStatus,
                'moderated_by' => $admin->id,
                'moderated_at' => now(),
            ];

            if ($newStatus === Review::STATUS_PUBLISHED) {
                $attributes['published_at'] = $lockedReview->published_at ?? now();
            }

            if ($newStatus === Review::STATUS_REJECTED) {
                $attributes['published_at'] = null;
            }

            $lockedReview->forceFill($attributes)->save();

            $metadata = [
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'risk_score' => $lockedReview->risk_score,
                'risk_reasons' => $lockedReview->risk_reasons ?? [],
            ];

            if ($newStatus === Review::STATUS_PUBLISHED) {
                $this->logs->moderatorPublished($lockedReview, $admin, $reason, $metadata);
            } else {
                $this->logs->moderatorRejected($lockedReview, $admin, $reason, $metadata);
            }

            $this->logs->statusChanged($lockedReview, $oldStatus, $newStatus, $admin, $reason, [
                'risk_score' => $lockedReview->risk_score,
                'risk_reasons' => $lockedReview->risk_reasons ?? [],
            ]);

            return $lockedReview->refresh()->load(['user', 'property', 'booking']);
        });
    }

    private function ensureAllowed(string $oldStatus, string $newStatus): void
    {
        if ($oldStatus === Review::STATUS_REJECTED) {
            throw $this->conflict('This review has already been rejected.');
        }

        if ($oldStatus === Review::STATUS_PUBLISHED && $newStatus === Review::STATUS_PUBLISHED) {
            throw $this->conflict('This review is already published.');
        }

        if ($oldStatus === Review::STATUS_PENDING_REVIEW) {
            return;
        }

        if ($oldStatus === Review::STATUS_PUBLISHED && $newStatus === Review::STATUS_REJECTED) {
            return;
        }

        throw $this->conflict('This review cannot be moderated in its current state.');
    }

    private function conflict(string $message): HttpResponseException
    {
        return new HttpResponseException(response()->json([
            'message' => $message,
        ], 409));
    }
}
