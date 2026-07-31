<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\StoreReportRequest;
use App\Http\Resources\ReportResource;
use App\Services\Reports\CreateReportService;

class ReportController extends Controller
{
    public function store(StoreReportRequest $request, CreateReportService $service)
    {
        $report = $service->create($request->validated(), $request->user());

        return response()->json([
            'message' => 'Report submitted successfully.',
            'report' => (new ReportResource($report))->resolve(),
        ], 201);
    }
}
