<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Notification;
use App\Models\Property;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    // ── GET /api/bookings ─────────────────────────────────────────────────────
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
            ->where('status', Booking::STATUS_ACCEPTED)
            ->whereDate('start_date', '<', $request->end_date)
            ->whereDate('end_date', '>', $request->start_date)
            ->exists();

        if ($overlap) {
            return response()->json([
                'message' => 'Property is already booked for these dates.',
            ], 422);
        }

        // ✅ إنشاء الحجز بحالة pending
        $nights = Carbon::parse($request->start_date)->diffInDays(Carbon::parse($request->end_date));

        if ($nights < 1) {
            return response()->json([
                'message' => 'The booking must be at least one night.',
            ], 422);
        }

        $booking = Booking::create([
            'user_id'     => Auth::id(),
            'property_id' => $request->property_id,
            'start_date'  => $request->start_date,
            'end_date'    => $request->end_date,
            'total_price' => $nights * $property->price_per_night,
            'status'      => Booking::STATUS_PENDING,
        ]);

        $booker   = Auth::user();

        // ✅ notification للمالك فقط إذا كان غير الحاجز
        if ($property->user_id !== Auth::id()) {
            Notification::create([
                'user_id' => $property->user_id,
                'message' => json_encode([
                    'type'        => 'booking_request',
                    'booking_id'  => $booking->id,
                    'property_id' => $property->id,
                    'booker_id'   => Auth::id(),          // ← مهم للـ Chat
                    'booker_name' => $booker->name,
                    'property'    => $property->title,
                    'property_title' => $property->title,
                    'guest_name' => $booker->name,
                    'check_in' => $request->start_date,
                    'check_out' => $request->end_date,
                    'start_date'  => $request->start_date,
                    'end_date'    => $request->end_date,
                    'text'        => "{$booker->name} requested to book \"{$property->title}\" from {$request->start_date} to {$request->end_date}.",
                ]),
            ]);

            // notification للحاجز — طلبه قيد الانتظار
            Notification::create([
                'user_id' => Auth::id(),
                'message' => json_encode([
                    'type'       => 'booking_pending',
                    'booking_id' => $booking->id,
                    'property_id' => $property->id,
                    'text'       => "Your booking request for \"{$property->title}\" from {$request->start_date} to {$request->end_date} is awaiting owner approval.",
                ]),
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

        if ($booking->status !== Booking::STATUS_PENDING) {
            return response()->json(['error' => 'Only pending bookings can be accepted.'], 422);
        }

        $overlap = Booking::where('property_id', $booking->property_id)
            ->where('id', '!=', $booking->id)
            ->where('status', Booking::STATUS_ACCEPTED)
            ->whereDate('start_date', '<', $booking->end_date)
            ->whereDate('end_date', '>', $booking->start_date)
            ->exists();

        if ($overlap) {
            return response()->json([
                'message' => 'These dates are no longer available.',
            ], 409);
        }

        $booking->update(['status' => Booking::STATUS_ACCEPTED]);

        // notification للحاجز — تم القبول
        Notification::create([
            'user_id' => $booking->user_id,
            'message' => json_encode([
                'type'       => 'booking_accepted',
                'booking_id' => $booking->id,
                'property_id' => $property->id,
                'text'       => "✅ Your booking for \"{$property->title}\" from {$booking->start_date} to {$booking->end_date} has been accepted!",
            ]),
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

        if ($booking->status !== Booking::STATUS_PENDING) {
            return response()->json(['error' => 'Only pending bookings can be rejected.'], 422);
        }

        $booking->update(['status' => Booking::STATUS_REJECTED]);

        // notification للحاجز — تم الرفض
        Notification::create([
            'user_id' => $booking->user_id,
            'message' => json_encode([
                'type'       => 'booking_rejected',
                'booking_id' => $booking->id,
                'property_id' => $property->id,
                'text'       => "❌ Your booking request for \"{$property->title}\" from {$booking->start_date} to {$booking->end_date} was declined.",
            ]),
        ]);

        return response()->json([
            'message' => 'Booking rejected.',
            'data'    => $booking,
        ]);
    }

    public function cancel(Request $request, $id)
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:1000',
        ]);

        $booking = Booking::with(['property', 'user'])->findOrFail($id);
        $user = Auth::user();

        $isGuest = (int) $booking->user_id === (int) $user->id;
        $isOwner = (int) $booking->property->user_id === (int) $user->id;

        if (! $isGuest && ! $isOwner) {
            return response()->json([
                'message' => 'You are not allowed to cancel this booking.',
            ], 403);
        }

        if ($booking->status === Booking::STATUS_CANCELLED) {
            return response()->json([
                'message' => 'This booking has already been cancelled.',
            ], 409);
        }

        if ($booking->hasStayStarted()) {
            return response()->json([
                'message' => 'This stay has already started and cannot be cancelled.',
            ], 409);
        }

        $actor = null;
        if ($isGuest && $booking->canBeCancelledByGuest($user)) {
            $actor = Booking::CANCELLATION_ACTOR_GUEST;
        } elseif ($isOwner && $booking->canBeCancelledByOwner($user)) {
            $actor = Booking::CANCELLATION_ACTOR_OWNER;
        }

        if (! $actor) {
            return response()->json([
                'message' => 'This booking can no longer be cancelled.',
            ], 409);
        }

        $reason = trim((string) ($validated['reason'] ?? ''));
        $booking->cancelBy($user, $actor, $reason !== '' ? $reason : null);
        $booking->refresh()->load(['property.images', 'user', 'cancelledBy']);

        $this->notifyBookingCancelled($booking);

        return response()->json([
            'message' => 'Booking cancelled successfully.',
            'booking' => $booking,
        ]);
    }

    // ── حجوزات ملكيات المالك ─────────────────────────────────────────────────
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

    public function destroy(Request $request, $id)
    {
        return $this->cancel($request, $id);
    }

    private function notifyBookingCancelled(Booking $booking): void
    {
        $property = $booking->property;
        $cancelledAt = optional($booking->cancelled_at)->toIso8601String();
        $reason = $booking->cancellation_reason;

        if ($booking->cancellation_actor === Booking::CANCELLATION_ACTOR_GUEST) {
            Notification::create([
                'user_id' => $property->user_id,
                'message' => json_encode([
                    'type' => 'booking_cancelled_by_guest',
                    'booking_id' => $booking->id,
                    'property_id' => $property->id,
                    'property_title' => $property->title,
                    'guest_name' => $booking->user?->name,
                    'cancelled_by' => Booking::CANCELLATION_ACTOR_GUEST,
                    'cancellation_reason' => $reason,
                    'cancelled_at' => $cancelledAt,
                    'text' => "{$booking->user?->name} cancelled their booking for \"{$property->title}\".",
                ]),
            ]);

            return;
        }

        Notification::create([
            'user_id' => $booking->user_id,
            'message' => json_encode([
                'type' => 'booking_cancelled_by_owner',
                'booking_id' => $booking->id,
                'property_id' => $property->id,
                'property_title' => $property->title,
                'owner_name' => Auth::user()?->name,
                'cancelled_by' => Booking::CANCELLATION_ACTOR_OWNER,
                'cancellation_reason' => $reason,
                'cancelled_at' => $cancelledAt,
                'text' => "Your booking for \"{$property->title}\" was cancelled by the owner.",
            ]),
        ]);
    }
}
