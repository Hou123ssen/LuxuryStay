<?php

namespace App\Services\Reports;

use App\Models\Report;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;

class ReportModerationService
{
    public function markReviewed(Report $report, User $admin, ?string $notes = null): Report
    {
        return $this->transition($report, $admin, Report::STATUS_REVIEWED, $notes);
    }

    public function resolve(Report $report, User $admin, ?string $notes = null): Report
    {
        return $this->transition($report, $admin, Report::STATUS_RESOLVED, $notes);
    }

    public function reject(Report $report, User $admin, ?string $notes = null): Report
    {
        return $this->transition($report, $admin, Report::STATUS_REJECTED, $notes);
    }

    private function transition(Report $report, User $admin, string $status, ?string $notes): Report
    {
        if (in_array($report->status, [Report::STATUS_RESOLVED, Report::STATUS_REJECTED], true)) {
            throw new HttpResponseException(response()->json([
                'message' => 'This report has already been closed.',
            ], 409));
        }

        if ($report->status === Report::STATUS_REVIEWED && $status === Report::STATUS_REVIEWED) {
            throw new HttpResponseException(response()->json([
                'message' => 'This report has already been reviewed.',
            ], 409));
        }

        $now = now();
        $attributes = [
            'status' => $status,
            'reviewed_by_user_id' => $admin->id,
            'reviewed_at' => $report->reviewed_at ?? $now,
        ];

        if ($notes !== null) {
            $attributes['admin_notes'] = $notes;
        }

        if ($status === Report::STATUS_RESOLVED) {
            $attributes['resolved_at'] = $now;
        }

        $report->forceFill($attributes)->save();

        return $report->refresh()->load(['property', 'reporter', 'reportedUser']);
    }
}
