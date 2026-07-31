<?php

namespace App\Http\Controllers;

use App\Http\Requests\Bookings\CancelBookingRequest;
use App\Http\Requests\Bookings\StoreBookingRequest;
use App\Models\Booking;
use App\Models\Property;
use App\Services\Bookings\AcceptBookingService;
use App\Services\Bookings\CancelBookingService;
use App\Services\Bookings\CreateBookingService;
use App\Services\Bookings\RejectBookingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        $query = Booking::with('property.images')
            ->where('user_id', Auth::id())
            ->latest();

        if ($request->query('tab') === 'upcoming') {
            $query->whereDate('end_date', '>=', Carbon::today());
        }

        if ($request->query('tab') === 'past') {
            $query->whereDate('end_date', '<', Carbon::today());
        }

        $bookings = $query
            ->paginate($this->perPage($request, 6))
            ->withQueryString();

        return $this->paginatedResponse($bookings);
    }

    public function store(StoreBookingRequest $request, CreateBookingService $bookings)
    {
        $booking = $bookings->create($request->user(), $request->validated());

        return response()->json([
            'message' => 'Booking request sent successfully.',
            'data' => $booking,
        ], 201);
    }

    public function accept($id, AcceptBookingService $bookings)
    {
        $booking = $bookings->accept((int) $id, Auth::user());

        return response()->json([
            'message' => 'Booking accepted.',
            'data' => $booking,
        ]);
    }

    public function reject($id, RejectBookingService $bookings)
    {
        $booking = $bookings->reject((int) $id, Auth::user());

        return response()->json([
            'message' => 'Booking rejected.',
            'data' => $booking,
        ]);
    }

    public function cancel(CancelBookingRequest $request, $id, CancelBookingService $bookings)
    {
        $booking = $bookings->cancel((int) $id, $request->user(), $request->validated('reason'));

        return response()->json([
            'message' => 'Booking cancelled successfully.',
            'booking' => $booking,
        ]);
    }

    public function ownerBookings(Request $request)
    {
        $propertyIds = Property::where('user_id', Auth::id())->pluck('id');

        $bookings = Booking::with(['property.images', 'user'])
            ->whereIn('property_id', $propertyIds)
            ->latest()
            ->paginate($this->perPage($request, 6))
            ->withQueryString();

        return $this->paginatedResponse($bookings);
    }

    public function destroy(CancelBookingRequest $request, $id, CancelBookingService $bookings)
    {
        return $this->cancel($request, $id, $bookings);
    }
}
