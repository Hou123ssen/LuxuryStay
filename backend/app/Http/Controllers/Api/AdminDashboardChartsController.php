<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminDashboardChartsService;
use App\Services\Admin\AdminDashboardGuard;
use Illuminate\Http\Request;

class AdminDashboardChartsController extends Controller
{
    public function index(
        Request $request,
        AdminDashboardGuard $guard,
        AdminDashboardChartsService $charts
    ) {
        $guard->authorize($request->user());

        return response()->json([
            'data' => $charts->overview($request),
        ]);
    }
}
