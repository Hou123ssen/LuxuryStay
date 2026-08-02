<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminDashboardChartsService;
use App\Services\Admin\AdminDashboardGuard;
use App\Services\Analytics\DemoAnalyticsDataService;
use Illuminate\Http\Request;

class AdminDashboardChartsController extends Controller
{
    public function index(
        Request $request,
        AdminDashboardGuard $guard,
        AdminDashboardChartsService $charts,
        DemoAnalyticsDataService $demoData
    ) {
        $guard->authorize($request->user());
        $includeDemo = $demoData->includeDemo($request);

        return response()->json([
            'data' => $charts->overview($request, $includeDemo),
            'meta' => [
                'demo_data' => $demoData->meta($includeDemo),
            ],
        ]);
    }
}
