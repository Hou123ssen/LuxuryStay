<?php

namespace App\Support;

use App\Models\Property;

class PropertyRating
{
    private const PLATFORM_AVERAGE = 4.0;
    private const MINIMUM_REVIEWS = 5;

    public static function apply(Property $property): Property
    {
        $count = (int) ($property->reviews_count ?? 0);
        $average = $property->reviews_avg_rating;
        $averageRating = $average === null ? null : round((float) $average, 1);
        $rankingScore = null;
        $hasUnresolvedHighRiskPendingReviews = (int) ($property->pending_high_risk_reviews_count ?? 0) > 0;

        if ($count > 0 && $averageRating !== null) {
            $rankingScore = round(self::weightedScore((float) $average, $count), 1);
        }

        $property->setAttribute('average_rating', $averageRating);
        $property->setAttribute('reviews_count', $count);
        $property->setAttribute('rating_state', self::state($count));
        $property->setAttribute('ranking_score', $rankingScore);
        $property->setAttribute('public_rating', $rankingScore);
        $property->setAttribute('rating_label', self::label($count));
        $property->setAttribute('trust_badge', self::trustBadge($averageRating, $count, $hasUnresolvedHighRiskPendingReviews));
        $property->setAttribute('trust_label', self::trustLabel($property->trust_badge));
        unset($property->reviews_avg_rating);
        unset($property->pending_high_risk_reviews_count);

        return $property;
    }

    public static function weightedScoreSql(string $averageColumn, string $countColumn): string
    {
        return sprintf(
            'CASE WHEN %2$s > 0 THEN ((%2$s * %1$s) + (%3$d * %4$.1F)) / (%2$s + %3$d) ELSE NULL END',
            $averageColumn,
            $countColumn,
            self::MINIMUM_REVIEWS,
            self::PLATFORM_AVERAGE,
        );
    }

    private static function weightedScore(float $average, int $count): float
    {
        return (($count * $average) + (self::MINIMUM_REVIEWS * self::PLATFORM_AVERAGE))
            / ($count + self::MINIMUM_REVIEWS);
    }

    private static function state(int $count): string
    {
        if ($count === 0) {
            return 'new';
        }

        if ($count < self::MINIMUM_REVIEWS) {
            return 'forming';
        }

        return 'established';
    }

    private static function label(int $count): ?string
    {
        if ($count === 0) {
            return 'New';
        }

        if ($count < self::MINIMUM_REVIEWS) {
            $stayLabel = $count === 1 ? 'verified stay' : 'verified stays';

            return sprintf('Rating forming · %d %s', $count, $stayLabel);
        }

        return null;
    }

    private static function trustBadge(?float $averageRating, int $count, bool $hasUnresolvedHighRiskPendingReviews): ?string
    {
        if ($hasUnresolvedHighRiskPendingReviews || self::state($count) !== 'established' || $averageRating === null) {
            return null;
        }

        if ($count >= 50 && $averageRating >= 4.8) {
            return 'top_rated';
        }

        if ($count >= 20 && $averageRating >= 4.7) {
            return 'highly_trusted';
        }

        if ($count >= 10 && $averageRating >= 4.5) {
            return 'trusted';
        }

        return null;
    }

    private static function trustLabel(?string $trustBadge): ?string
    {
        return match ($trustBadge) {
            'trusted' => 'Trusted',
            'highly_trusted' => 'Highly trusted',
            'top_rated' => 'Top rated',
            default => null,
        };
    }
}
