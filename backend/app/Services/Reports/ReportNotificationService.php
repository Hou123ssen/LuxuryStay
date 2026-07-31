<?php

namespace App\Services\Reports;

use App\Models\Notification;
use App\Models\Report;
use App\Models\User;

class ReportNotificationService
{
    public function notifyAdmins(Report $report, User $reporter): void
    {
        User::where('role', 'admin')->each(function (User $admin) use ($report, $reporter) {
            Notification::create([
                'user_id' => $admin->id,
                'message' => 'New property report submitted.',
            ]);
        });
    }

    public function metadata(Report $report, User $reporter): array
    {
        return [
            'report_id' => $report->id,
            'property_id' => $report->property_id,
            'booking_id' => $report->booking_id,
            'category' => $report->category,
            'severity' => $report->severity,
            'reporter_name' => $reporter->name,
            'created_at' => $report->created_at,
        ];
    }
}
