<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminDashboardGuard;
use App\Services\Admin\AdminGeographyAnalyticsService;
use App\Services\Analytics\DemoAnalyticsDataService;
use Illuminate\Http\Request;

class AdminGeographyController extends Controller
{
    public function index(
        Request $request,
        AdminDashboardGuard $guard,
        AdminGeographyAnalyticsService $geography,
        DemoAnalyticsDataService $demoData
    ) {
        $guard->authorize($request->user());
        $includeDemo = $demoData->includeDemo($request);

        return response()->json([
            'data' => $geography->overview($request, $includeDemo),
            'meta' => [
                'demo_data' => $demoData->meta($includeDemo),
            ],
        ]);
    }
}
