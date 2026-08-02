<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminDashboardGuard;
use App\Services\Admin\AdminDashboardOverviewService;
use App\Services\Analytics\DemoAnalyticsDataService;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    public function overview(
        Request $request,
        AdminDashboardGuard $guard,
        AdminDashboardOverviewService $overview,
        DemoAnalyticsDataService $demoData
    ) {
        $guard->authorize($request->user());
        $includeDemo = $demoData->includeDemo($request);

        return response()->json([
            'data' => $overview->overview($includeDemo),
            'meta' => [
                'demo_data' => $demoData->meta($includeDemo),
            ],
        ]);
    }
}
