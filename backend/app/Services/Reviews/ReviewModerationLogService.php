<?php

namespace App\Services\Reviews;

use App\Models\Review;
use App\Models\ReviewModerationLog;
use App\Models\User;

class ReviewModerationLogService
{
    public function created(Review $review, ?User $actor = null, array $metadata = []): ReviewModerationLog
    {
        return $this->write($review, ReviewModerationLog::ACTION_CREATED, $actor, null, $metadata);
    }

    public function autoPublished(Review $review, array $metadata = []): ReviewModerationLog
    {
        return $this->write($review, ReviewModerationLog::ACTION_AUTO_PUBLISHED, null, null, $metadata);
    }

    public function autoFlagged(Review $review, array $metadata = []): ReviewModerationLog
    {
        return $this->write($review, ReviewModerationLog::ACTION_AUTO_FLAGGED, null, null, $metadata);
    }

    public function statusChanged(
        Review $review,
        string $oldStatus,
        string $newStatus,
        ?User $actor = null,
        ?string $reason = null,
        array $metadata = []
    ): ReviewModerationLog {
        return $this->write($review, ReviewModerationLog::ACTION_STATUS_CHANGED, $actor, $reason, array_merge($metadata, [
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
        ]));
    }

    public function moderatorPublished(Review $review, User $actor, ?string $reason = null, array $metadata = []): ReviewModerationLog
    {
        return $this->write($review, ReviewModerationLog::ACTION_MODERATOR_PUBLISHED, $actor, $reason, $metadata);
    }

    public function moderatorRejected(Review $review, User $actor, ?string $reason = null, array $metadata = []): ReviewModerationLog
    {
        return $this->write($review, ReviewModerationLog::ACTION_MODERATOR_REJECTED, $actor, $reason, $metadata);
    }

    private function write(
        Review $review,
        string $action,
        ?User $actor = null,
        ?string $reason = null,
        array $metadata = []
    ): ReviewModerationLog {
        return ReviewModerationLog::create([
            'review_id' => $review->id,
            'actor_user_id' => $actor?->id,
            'action' => $action,
            'reason' => $reason,
            'metadata' => $this->sanitizeMetadata($metadata),
            'created_at' => now(),
        ]);
    }

    private function sanitizeMetadata(array $metadata): array
    {
        unset(
            $metadata['ip'],
            $metadata['raw_ip'],
            $metadata['user_agent'],
            $metadata['raw_user_agent'],
            $metadata['ip_hash'],
            $metadata['user_agent_hash'],
            $metadata['threshold'],
            $metadata['hash_secret']
        );

        return $metadata;
    }
}
