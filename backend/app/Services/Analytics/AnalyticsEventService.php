<?php

namespace App\Services\Analytics;

use App\Models\AnalyticsEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AnalyticsEventService
{
    public function __construct(
        private readonly CountryResolver $countryResolver,
        private readonly AnalyticsHashService $hashService,
    ) {
    }

    public function recordUserRegistered(User $user, Request $request, array $metadata = []): void
    {
        $this->record($user, AnalyticsEvent::TYPE_USER_REGISTERED, $request, $metadata);
    }

    public function recordUserLoggedIn(User $user, Request $request, array $metadata = []): void
    {
        $this->record($user, AnalyticsEvent::TYPE_USER_LOGGED_IN, $request, $metadata);
    }

    private function record(User $user, string $eventType, Request $request, array $metadata): void
    {
        try {
            $country = $this->countryResolver->resolve($request);
            $occurredAt = now();

            if (Schema::hasTable('analytics_events')) {
                AnalyticsEvent::create([
                    'user_id' => $user->id,
                    'event_type' => $eventType,
                    'country_code' => $country['country_code'],
                    'country_name' => $country['country_name'],
                    'country_source' => $country['country_source'],
                    'region_name' => $country['region_name'],
                    'city_name' => $country['city_name'],
                    'ip_hash' => $this->hashService->hash($request->ip()),
                    'user_agent_hash' => $this->hashService->hash($request->userAgent()),
                    'metadata' => $this->safeMetadata($metadata),
                    'occurred_at' => $occurredAt,
                ]);
            }

            $this->updateUserCountrySummary($user, $eventType, $country, $occurredAt);
        } catch (Throwable $exception) {
            Log::warning('Analytics event recording failed.', [
                'event_type' => $eventType,
                'user_id' => $user->id,
                'exception' => $exception::class,
            ]);
        }
    }

    private function updateUserCountrySummary(User $user, string $eventType, array $country, $occurredAt): void
    {
        $updates = [];

        if ($eventType === AnalyticsEvent::TYPE_USER_REGISTERED) {
            if (Schema::hasColumn('users', 'registered_country_code') && $user->registered_country_code === null) {
                $updates['registered_country_code'] = $country['country_code'];
            }

            if (Schema::hasColumn('users', 'registered_country_name') && $user->registered_country_name === null) {
                $updates['registered_country_name'] = $country['country_name'];
            }

            if (Schema::hasColumn('users', 'registered_region_name') && $user->registered_region_name === null) {
                $updates['registered_region_name'] = $country['region_name'];
            }

            if (Schema::hasColumn('users', 'registered_city_name') && $user->registered_city_name === null) {
                $updates['registered_city_name'] = $country['city_name'];
            }
        }

        if (Schema::hasColumn('users', 'last_seen_country_code')) {
            $updates['last_seen_country_code'] = $country['country_code'];
        }

        if (Schema::hasColumn('users', 'last_seen_country_name')) {
            $updates['last_seen_country_name'] = $country['country_name'];
        }

        if (Schema::hasColumn('users', 'last_seen_region_name')) {
            $updates['last_seen_region_name'] = $country['region_name'];
        }

        if (Schema::hasColumn('users', 'last_seen_city_name')) {
            $updates['last_seen_city_name'] = $country['city_name'];
        }

        if (Schema::hasColumn('users', 'last_seen_at')) {
            $updates['last_seen_at'] = $occurredAt;
        }

        if ($updates !== []) {
            $user->forceFill($updates)->save();
        }
    }

    private function safeMetadata(array $metadata): array
    {
        return collect($metadata)
            ->only(['source'])
            ->filter(fn ($value) => is_scalar($value) || $value === null)
            ->all();
    }
}
