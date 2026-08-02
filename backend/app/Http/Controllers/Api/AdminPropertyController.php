<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminPropertyDetailResource;
use App\Http\Resources\AdminPropertyResource;
use App\Models\Property;
use App\Services\Admin\AdminDashboardGuard;
use App\Services\Admin\AdminPropertyQueryService;
use Illuminate\Http\Request;

class AdminPropertyController extends Controller
{
    public function index(Request $request, AdminDashboardGuard $guard, AdminPropertyQueryService $properties)
    {
        $guard->authorize($request->user());

        return AdminPropertyResource::collection($properties->paginate($request));
    }

    public function show(Request $request, Property $property, AdminDashboardGuard $guard, AdminPropertyQueryService $properties)
    {
        $guard->authorize($request->user());

        return new AdminPropertyDetailResource($properties->forDetail($property));
    }
}
