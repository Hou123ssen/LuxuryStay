<?php

namespace App\Services\Reviews;

use App\Models\Booking;
use App\Models\Property;
use App\Models\Review;
use App\Models\User;

class ReviewRiskService
{
    public function assess(User $user, Property $property, Booking $booking, array $data, array $context = []): array
    {
        $reasons = [];
        $score = 0;
        $ipHash = $this->hash($context['ip'] ?? null);
        $userAgentHash = $this->hash($context['user_agent'] ?? null);

        if ($this->accountTooNew($user)) {
            $this->addReason($reasons, $score, 'ACCOUNT_TOO_NEW');
        }

        if ($this->hasReviewBurst($property)) {
            $this->addReason($reasons, $score, 'REVIEW_BURST');
        }

        if ($this->hasDuplicateContent($property, $data['comment'] ?? null)) {
            $this->addReason($reasons, $score, 'DUPLICATE_CONTENT');
        }

        if ($this->hasSharedNetworkCluster($property, $user, $ipHash)) {
            $this->addReason($reasons, $score, 'SHARED_NETWORK_CLUSTER');
        }

        if ($this->hasRecentUserReviewPattern($user)) {
            $this->addReason($reasons, $score, 'RATE_LIMIT_PATTERN');
        }

        if ((int) $booking->user_id !== (int) $user->id || (int) $booking->property_id !== (int) $property->id) {
            $this->addReason($reasons, $score, 'INVALID_BOOKING_SIGNAL');
        }

        return [
            'risk_score' => $score,
            'risk_reasons' => $reasons ?: null,
            'ip_hash' => $ipHash,
            'user_agent_hash' => $userAgentHash,
        ];
    }

    public function isHighRisk(int $score): bool
    {
        return $score >= (int) config('reviews.risk.high_risk_threshold', 70);
    }

    private function accountTooNew(User $user): bool
    {
        if (! $user->created_at) {
            return false;
        }

        return $user->created_at->gt(now()->subHours((int) config('reviews.risk.account_age_threshold_hours', 24)));
    }

    private function hasReviewBurst(Property $property): bool
    {
        $windowStart = now()->subMinutes((int) config('reviews.risk.burst_window_minutes', 60));
        $maxReviews = (int) config('reviews.risk.max_reviews_per_property_in_burst_window', 3);

        return Review::where('property_id', $property->id)
            ->where('created_at', '>=', $windowStart)
            ->count() >= $maxReviews;
    }

    private function hasDuplicateContent(Property $property, ?string $comment): bool
    {
        $normalizedComment = $this->normalizeComment($comment);

        if ($normalizedComment === '') {
            return false;
        }

        $threshold = (int) config('reviews.risk.duplicate_comment_similarity_threshold', 90);

        return Review::where('property_id', $property->id)
            ->whereNotNull('comment')
            ->pluck('comment')
            ->contains(function ($existingComment) use ($normalizedComment, $threshold) {
                $existing = $this->normalizeComment($existingComment);

                if ($existing === '') {
                    return false;
                }

                similar_text($normalizedComment, $existing, $percent);

                return $percent >= $threshold;
            });
    }

    private function hasSharedNetworkCluster(Property $property, User $user, ?string $ipHash): bool
    {
        if (! $ipHash) {
            return false;
        }

        $windowStart = now()->subMinutes((int) config('reviews.risk.shared_network_window_minutes', 60));
        $maxUsers = (int) config('reviews.risk.max_shared_network_users', 2);

        $distinctUsers = Review::where('property_id', $property->id)
            ->where('ip_hash', $ipHash)
            ->where('created_at', '>=', $windowStart)
            ->where('user_id', '!=', $user->id)
            ->distinct()
            ->count('user_id');

        return $distinctUsers >= $maxUsers;
    }

    private function hasRecentUserReviewPattern(User $user): bool
    {
        $windowStart = now()->subMinutes((int) config('reviews.risk.burst_window_minutes', 60));
        $maxReviews = (int) config('reviews.risk.max_reviews_per_property_in_burst_window', 3);

        return Review::where('user_id', $user->id)
            ->where('created_at', '>=', $windowStart)
            ->count() >= $maxReviews;
    }

    private function addReason(array &$reasons, int &$score, string $code): void
    {
        $reasons[] = $code;
        $score += (int) config("reviews.risk.weights.$code", 0);
    }

    private function hash(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return hash_hmac('sha256', $value, (string) config('reviews.risk.hash_secret'));
    }

    private function normalizeComment(?string $comment): string
    {
        $comment = strtolower(trim((string) $comment));

        return preg_replace('/\s+/', ' ', $comment) ?? '';
    }
}
