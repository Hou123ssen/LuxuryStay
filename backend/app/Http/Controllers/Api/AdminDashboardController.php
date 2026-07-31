<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminDashboardGuard;
use App\Services\Admin\AdminDashboardOverviewService;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    public function overview(
        Request $request,
        AdminDashboardGuard $guard,
        AdminDashboardOverviewService $overview
    ) {
        $guard->authorize($request->user());

        return response()->json([
            'data' => $overview->overview(),
        ]);
    }
}
