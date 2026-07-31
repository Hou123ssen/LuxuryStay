<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ModerateReportRequest;
use App\Http\Resources\AdminReportResource;
use App\Models\Report;
use App\Services\Reports\ReportAdminGuard;
use App\Services\Reports\ReportModerationService;
use App\Services\Reports\ReportQueryService;
use Illuminate\Http\Request;

class AdminReportController extends Controller
{
    public function index(Request $request, ReportAdminGuard $guard, ReportQueryService $reports)
    {
        $guard->authorize($request->user());

        $paginator = $reports->paginate($request, $this->perPage($request, 15));
        $paginator->getCollection()->transform(function (Report $report) {
            return (new AdminReportResource($report))->resolve();
        });

        return $this->paginatedResponse($paginator);
    }

    public function show(Request $request, Report $report, ReportAdminGuard $guard, ReportQueryService $reports)
    {
        $guard->authorize($request->user());

        return response()->json([
            'data' => (new AdminReportResource($reports->find($report->id)))->resolve(),
        ]);
    }

    public function review(
        ModerateReportRequest $request,
        Report $report,
        ReportAdminGuard $guard,
        ReportModerationService $moderation
    ) {
        $guard->authorize($request->user());

        return $this->moderationResponse(
            $moderation->markReviewed($report, $request->user(), $request->validated('admin_notes')),
            'Report marked as reviewed.'
        );
    }

    public function resolve(
        ModerateReportRequest $request,
        Report $report,
        ReportAdminGuard $guard,
        ReportModerationService $moderation
    ) {
        $guard->authorize($request->user());

        return $this->moderationResponse(
            $moderation->resolve($report, $request->user(), $request->validated('admin_notes')),
            'Report resolved.'
        );
    }

    public function reject(
        ModerateReportRequest $request,
        Report $report,
        ReportAdminGuard $guard,
        ReportModerationService $moderation
    ) {
        $guard->authorize($request->user());

        return $this->moderationResponse(
            $moderation->reject($report, $request->user(), $request->validated('admin_notes')),
            'Report rejected.'
        );
    }

    private function moderationResponse(Report $report, string $message)
    {
        return response()->json([
            'message' => $message,
            'data' => (new AdminReportResource($report))->resolve(),
        ]);
    }
}
