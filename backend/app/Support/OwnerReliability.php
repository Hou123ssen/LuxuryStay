<?php

namespace App\Support;

use App\Models\Booking;
use App\Models\Property;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class OwnerReliability
{
    private const FORMING_MINIMUM = 5;
    private const ESTABLISHED_MINIMUM = 20;

    public static function apply(Property $property): Property
    {
        $metrics = self::forOwnerIds(collect([$property->user_id]))->get((int) $property->user_id);

        return self::applyMetrics($property, $metrics);
    }

    public static function applyToCollection(Collection|EloquentCollection $properties): Collection|EloquentCollection
    {
        $metricsByOwner = self::forOwnerIds($properties->pluck('user_id'));

        $properties->each(function (Property $property) use ($metricsByOwner) {
            self::applyMetrics($property, $metricsByOwner->get((int) $property->user_id));
        });

        return $properties;
    }

    public static function forOwnerIds(Collection $ownerIds): Collection
    {
        $ownerIds = $ownerIds
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($ownerIds->isEmpty()) {
            return collect();
        }

        $rows = Booking::query()
            ->join('properties', 'bookings.property_id', '=', 'properties.id')
            ->whereIn('properties.user_id', $ownerIds)
            ->select('properties.user_id as owner_id')
            ->selectRaw(
                "SUM(CASE WHEN bookings.status IN (?, ?) OR bookings.cancellation_actor = ? THEN 1 ELSE 0 END) as owner_accepted_bookings_count",
                [Booking::STATUS_ACCEPTED, Booking::STATUS_COMPLETED, Booking::CANCELLATION_ACTOR_OWNER],
            )
            ->selectRaw(
                'SUM(CASE WHEN bookings.cancellation_actor = ? THEN 1 ELSE 0 END) as owner_cancelled_bookings_count',
                [Booking::CANCELLATION_ACTOR_OWNER],
            )
            ->groupBy('properties.user_id')
            ->get()
            ->keyBy(fn ($row) => (int) $row->owner_id);

        return $ownerIds->mapWithKeys(function (int $ownerId) use ($rows) {
            $row = $rows->get($ownerId);
            $acceptedCount = (int) ($row->owner_accepted_bookings_count ?? 0);
            $cancelledCount = (int) ($row->owner_cancelled_bookings_count ?? 0);

            return [$ownerId => self::payload($acceptedCount, $cancelledCount)];
        });
    }

    private static function applyMetrics(Property $property, ?array $metrics): Property
    {
        $property->setAttribute('owner_reliability', $metrics ?? self::payload(0, 0));

        return $property;
    }

    private static function payload(int $acceptedCount, int $cancelledCount): array
    {
        $rate = $acceptedCount > 0
            ? round(($cancelledCount / $acceptedCount) * 100, 1)
            : null;

        $state = self::state($acceptedCount);

        return [
            'owner_accepted_bookings_count' => $acceptedCount,
            'owner_cancelled_bookings_count' => $cancelledCount,
            'owner_cancellation_rate' => $rate,
            'owner_reliability_state' => $state,
            'owner_reliability_label' => self::label($state, $rate),
        ];
    }

    private static function state(int $acceptedCount): string
    {
        if ($acceptedCount < self::FORMING_MINIMUM) {
            return 'new';
        }

        if ($acceptedCount < self::ESTABLISHED_MINIMUM) {
            return 'forming';
        }

        return 'established';
    }

    private static function label(string $state, ?float $rate): string
    {
        if ($state === 'new') {
            return 'Reliability data not enough yet';
        }

        if ($state === 'forming') {
            return 'Reliability forming';
        }

        if ($rate !== null && $rate <= 5) {
            return 'Reliable host history';
        }

        if ($rate !== null && $rate <= 15) {
            return 'Moderate cancellation history';
        }

        return 'High cancellation history';
    }
}
