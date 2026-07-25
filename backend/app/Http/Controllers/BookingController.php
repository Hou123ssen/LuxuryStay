<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Notification;
use App\Models\Property;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    // ── GET /api/bookings ─────────────────────────────────────────────────────
    public function index()
    {
        $bookings = Booking::with('property.images')
            ->where('user_id', Auth::id())
            ->latest()
            ->get();

        return response()->json(['data' => $bookings]);
    }

    // ── POST /api/bookings ────────────────────────────────────────────────────
    public function store(Request $request)
    {
        $request->validate([
            'property_id' => 'required|exists:properties,id',
            'start_date'  => 'required|date|after_or_equal:today',
            'end_date'    => 'required|date|after:start_date',
        ]);

        // ✅ تحقق من overlap — فقط الحجوزات المقبولة تمنع الحجز
        $property = Property::findOrFail($request->property_id);

        if ($property->user_id === Auth::id()) {
            return response()->json([
                'message' => 'You cannot book your own property.',
            ], 403);
        }

        $overlap = Booking::where('property_id', $request->property_id)
            ->where('status', 'accepted')
            ->where(function ($q) use ($request) {
                $q->whereBetween('start_date', [$request->start_date, $request->end_date])
                    ->orWhereBetween('end_date',   [$request->start_date, $request->end_date])
                    ->orWhere(function ($q2) use ($request) {
                        $q2->where('start_date', '<=', $request->start_date)
                            ->where('end_date',   '>=', $request->end_date);
                    });
            })->exists();

        if ($overlap) {
            return response()->json([
                'message' => 'Property is already booked for these dates.',
            ], 422);
        }

        // ✅ إنشاء الحجز بحالة pending
        $booking = Booking::create([
            'user_id'     => Auth::id(),
            'property_id' => $request->property_id,
            'start_date'  => $request->start_date,
            'end_date'    => $request->end_date,
            'status'      => 'pending',
        ]);

        $booker   = Auth::user();

        // ✅ notification للمالك فقط إذا كان غير الحاجز
        if ($property->user_id !== Auth::id()) {
            Notification::create([
                'user_id' => $property->user_id,
                'message' => json_encode([
                    'type'        => 'booking_request',
                    'booking_id'  => $booking->id,
                    'booker_id'   => Auth::id(),          // ← مهم للـ Chat
                    'booker_name' => $booker->name,
                    'property'    => $property->title,
                    'start_date'  => $request->start_date,
                    'end_date'    => $request->end_date,
                    'text'        => "{$booker->name} requested to book \"{$property->title}\" from {$request->start_date} to {$request->end_date}.",
                ]),
                'read' => false,
            ]);

            // notification للحاجز — طلبه قيد الانتظار
            Notification::create([
                'user_id' => Auth::id(),
                'message' => json_encode([
                    'type'       => 'booking_pending',
                    'booking_id' => $booking->id,
                    'text'       => "Your booking request for \"{$property->title}\" from {$request->start_date} to {$request->end_date} is awaiting owner approval.",
                ]),
                'read' => false,
            ]);
        }

        return response()->json([
            'message' => 'Booking request sent successfully.',
            'data'    => $booking->load('property'),
        ], 201);
    }

    // ── POST /api/bookings/{id}/accept ────────────────────────────────────────
    public function accept($id)
    {
        $booking  = Booking::with('property', 'user')->findOrFail($id);
        $property = $booking->property;

        // فقط المالك يقدر يقبل
        if ($property->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ($booking->status !== 'pending') {
            return response()->json(['error' => 'Only pending bookings can be accepted.'], 422);
        }

        $booking->update(['status' => 'accepted']);

        // notification للحاجز — تم القبول
        Notification::create([
            'user_id' => $booking->user_id,
            'message' => json_encode([
                'type'       => 'booking_accepted',
                'booking_id' => $booking->id,
                'text'       => "✅ Your booking for \"{$property->title}\" from {$booking->start_date} to {$booking->end_date} has been accepted!",
            ]),
            'read' => false,
        ]);

        return response()->json([
            'message' => 'Booking accepted.',
            'data'    => $booking,
        ]);
    }

    // ── POST /api/bookings/{id}/reject ────────────────────────────────────────
    public function reject($id)
    {
        $booking  = Booking::with('property', 'user')->findOrFail($id);
        $property = $booking->property;

        // فقط المالك يقدر يرفض
        if ($property->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ($booking->status !== 'pending') {
            return response()->json(['error' => 'Only pending bookings can be rejected.'], 422);
        }

        $booking->update(['status' => 'rejected']);

        // notification للحاجز — تم الرفض
        Notification::create([
            'user_id' => $booking->user_id,
            'message' => json_encode([
                'type'       => 'booking_rejected',
                'booking_id' => $booking->id,
                'text'       => "❌ Your booking request for \"{$property->title}\" from {$booking->start_date} to {$booking->end_date} was declined.",
            ]),
            'read' => false,
        ]);

        return response()->json([
            'message' => 'Booking rejected.',
            'data'    => $booking,
        ]);
    }

    // ── حجوزات ملكيات المالك ─────────────────────────────────────────────────
    public function ownerBookings()
    {
        $propertyIds = Property::where('user_id', Auth::id())->pluck('id');

        $bookings = Booking::with(['property.images', 'user'])
            ->whereIn('property_id', $propertyIds)
            ->latest()
            ->get();

        return response()->json(['data' => $bookings]);
    }

    public function destroy($id)
    {
        $booking = Booking::findOrFail($id);
        if ($booking->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        if ($booking->status === 'accepted') {
            return response()->json(['error' => 'Cannot cancel an accepted booking.'], 422);
        }
        $booking->delete();
        return response()->json(['message' => 'Booking cancelled.']);
    }
}
