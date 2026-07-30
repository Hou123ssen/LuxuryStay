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
        $publicRating = null;

        if ($count > 0 && $averageRating !== null) {
            $publicRating = round(self::weightedScore((float) $average, $count), 1);
        }

        $property->setAttribute('average_rating', $averageRating);
        $property->setAttribute('reviews_count', $count);
        $property->setAttribute('public_rating', $publicRating);
        $property->setAttribute('rating_label', $count === 0 ? 'New' : null);
        unset($property->reviews_avg_rating);

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
}
