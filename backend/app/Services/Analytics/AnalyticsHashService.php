<?php

namespace App\Services\Analytics;

class AnalyticsHashService
{
    public function hash(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return hash_hmac('sha256', $value, (string) config('analytics.hash_key'));
    }
}
