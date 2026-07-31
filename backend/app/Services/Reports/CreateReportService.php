<?php

namespace App\Services\Reports;

use App\Models\Booking;
use App\Models\Property;
use App\Models\Report;
use App\Models\User;

class CreateReportService
{
    public function __construct(
        private readonly ReportGuard $reportGuard,
        private readonly ReportNotificationService $notificationService
    ) {
    }

    public function create(array $validated, User $reporter): Report
    {
        $property = Property::findOrFail($validated['property_id']);
        $booking = Booking::findOrFail($validated['booking_id']);

        $this->reportGuard->authorizeCreate((int) $reporter->id, $booking, $property);

        $report = Report::create([
            'reporter_user_id' => $reporter->id,
            'property_id' => $property->id,
            'booking_id' => $booking->id,
            'reported_user_id' => $property->user_id,
            'category' => $validated['category'],
            'description' => $validated['description'] ?? null,
            'status' => Report::STATUS_PENDING,
            'severity' => $this->severityFor($validated['category']),
        ]);

        $this->notificationService->notifyAdmins($report, $reporter);

        return $report;
    }

    private function severityFor(string $category): string
    {
        return in_array($category, [Report::CATEGORY_SCAM_OR_FRAUD, Report::CATEGORY_UNSAFE_PROPERTY], true)
            ? Report::SEVERITY_HIGH
            : Report::SEVERITY_NORMAL;
    }
}
