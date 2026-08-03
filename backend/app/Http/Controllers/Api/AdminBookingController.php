<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminBookingDetailResource;
use App\Http\Resources\AdminBookingResource;
use App\Models\Booking;
use App\Services\Admin\AdminBookingQueryService;
use App\Services\Admin\AdminDashboardGuard;
use Illuminate\Http\Request;

class AdminBookingController extends Controller
{
    public function index(Request $request, AdminDashboardGuard $guard, AdminBookingQueryService $bookings)
    {
        $guard->authorize($request->user());

        return AdminBookingResource::collection($bookings->paginate($request));
    }

    public function show(Request $request, Booking $booking, AdminDashboardGuard $guard, AdminBookingQueryService $bookings)
    {
        $guard->authorize($request->user());

        return new AdminBookingDetailResource($bookings->forDetail($booking));
    }
}
