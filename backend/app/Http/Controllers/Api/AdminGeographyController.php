<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminDashboardGuard;
use App\Services\Admin\AdminGeographyAnalyticsService;
use Illuminate\Http\Request;

class AdminGeographyController extends Controller
{
    public function index(
        Request $request,
        AdminDashboardGuard $guard,
        AdminGeographyAnalyticsService $geography
    ) {
        $guard->authorize($request->user());

        return response()->json([
            'data' => $geography->overview($request),
        ]);
    }
}
