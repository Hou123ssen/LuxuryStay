<?php

namespace App\Support;

use App\Models\Property;
use App\Models\Report;

class PropertyReportSignals
{
    public const STATE_CLEAR = 'clear';
    public const STATE_ATTENTION = 'attention';
    public const STATE_SERIOUS_ATTENTION = 'serious_attention';

    public static function forProperty(Property $property): array
    {
        return self::forPropertyId((int) $property->id);
    }

    public static function forPropertyId(int $propertyId): array
    {
        $row = Report::query()
            ->where('property_id', $propertyId)
            ->whereIn('status', Report::OPEN_STATUSES)
            ->selectRaw('COUNT(*) as unresolved_reports_count')
            ->selectRaw('SUM(CASE WHEN severity = ? THEN 1 ELSE 0 END) as unresolved_high_reports_count', [Report::SEVERITY_HIGH])
            ->selectRaw('SUM(CASE WHEN severity = ? THEN 1 ELSE 0 END) as unresolved_critical_reports_count', [Report::SEVERITY_CRITICAL])
            ->selectRaw('SUM(CASE WHEN category = ? THEN 1 ELSE 0 END) as unresolved_safety_reports_count', [Report::CATEGORY_UNSAFE_PROPERTY])
            ->selectRaw('SUM(CASE WHEN category = ? THEN 1 ELSE 0 END) as unresolved_fraud_reports_count', [Report::CATEGORY_SCAM_OR_FRAUD])
            ->first();

        $payload = [
            'unresolved_reports_count' => (int) ($row->unresolved_reports_count ?? 0),
            'unresolved_high_reports_count' => (int) ($row->unresolved_high_reports_count ?? 0),
            'unresolved_critical_reports_count' => (int) ($row->unresolved_critical_reports_count ?? 0),
            'unresolved_safety_reports_count' => (int) ($row->unresolved_safety_reports_count ?? 0),
            'unresolved_fraud_reports_count' => (int) ($row->unresolved_fraud_reports_count ?? 0),
        ];

        $payload['report_signal_state'] = self::state($payload);
        $payload['report_signal_label'] = self::label($payload['report_signal_state']);

        return $payload;
    }

    private static function state(array $payload): string
    {
        if ($payload['unresolved_reports_count'] === 0) {
            return self::STATE_CLEAR;
        }

        $seriousSignals = $payload['unresolved_high_reports_count']
            + $payload['unresolved_critical_reports_count']
            + $payload['unresolved_safety_reports_count']
            + $payload['unresolved_fraud_reports_count'];

        return $seriousSignals > 0
            ? self::STATE_SERIOUS_ATTENTION
            : self::STATE_ATTENTION;
    }

    private static function label(string $state): string
    {
        return match ($state) {
            self::STATE_ATTENTION => 'Unresolved reports under review',
            self::STATE_SERIOUS_ATTENTION => 'Serious unresolved reports under review',
            default => 'No unresolved report signals',
        };
    }
}
