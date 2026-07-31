<?php

namespace App\Services\Reports;

use App\Models\Report;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class ReportQueryService
{
    public function paginate(Request $request, int $perPage): LengthAwarePaginator
    {
        return $this->query($request)
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(int $reportId): Report
    {
        return Report::with(['property', 'reporter', 'reportedUser'])->findOrFail($reportId);
    }

    private function query(Request $request)
    {
        return Report::with(['property', 'reporter', 'reportedUser'])
            ->when($request->query('status'), function ($query, string $status) {
                $query->where('status', $status);
            })
            ->when($request->query('severity'), function ($query, string $severity) {
                $query->where('severity', $severity);
            })
            ->when($request->query('category'), function ($query, string $category) {
                $query->where('category', $category);
            });
    }
}
