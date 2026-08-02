<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminUserDetailResource;
use App\Http\Resources\AdminUserResource;
use App\Models\User;
use App\Services\Admin\AdminDashboardGuard;
use App\Services\Admin\AdminUserQueryService;
use App\Services\Analytics\DemoAnalyticsDataService;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    public function index(
        Request $request,
        AdminDashboardGuard $guard,
        AdminUserQueryService $users,
        DemoAnalyticsDataService $demoData
    ) {
        $guard->authorize($request->user());
        $includeDemo = $demoData->includeDemo($request);

        return AdminUserResource::collection($users->paginate($request, $includeDemo))
            ->additional([
                'meta' => [
                    'demo_data' => $demoData->meta($includeDemo),
                ],
            ]);
    }

    public function show(
        Request $request,
        User $user,
        AdminDashboardGuard $guard,
        AdminUserQueryService $users,
        DemoAnalyticsDataService $demoData
    ) {
        $guard->authorize($request->user());
        $includeDemo = $demoData->includeDemo($request);

        return (new AdminUserDetailResource($users->forDetail($user, $includeDemo)))
            ->additional([
                'meta' => [
                    'demo_data' => $demoData->meta($includeDemo),
                ],
            ]);
    }
}
