<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = Notification::where('user_id', Auth::id())
            ->latest()
            ->paginate($this->perPage($request, 10))
            ->withQueryString();

        $notifications->getCollection()->transform(function ($notification) {
            $decoded = json_decode($notification->message, true);

            return [
                'id' => $notification->id,
                'read' => $notification->isRead(),
                'created_at' => $notification->created_at,
                'message' => $decoded['text'] ?? $notification->message,
                'type' => $decoded['type'] ?? 'general',
                'booking_id' => $decoded['booking_id'] ?? null,
                'property_id' => $decoded['property_id'] ?? null,
                'property_title' => $decoded['property_title'] ?? $decoded['property'] ?? null,
                'guest_name' => $decoded['guest_name'] ?? $decoded['booker_name'] ?? null,
                'owner_name' => $decoded['owner_name'] ?? null,
                'check_in' => $decoded['check_in'] ?? $decoded['start_date'] ?? null,
                'check_out' => $decoded['check_out'] ?? $decoded['end_date'] ?? null,
                'booker_id' => $decoded['booker_id'] ?? null,
                'cancelled_by' => $decoded['cancelled_by'] ?? null,
                'cancellation_reason' => $decoded['cancellation_reason'] ?? null,
                'cancelled_at' => $decoded['cancelled_at'] ?? null,
            ];
        });

        return $this->paginatedResponse($notifications);
    }

    public function markAsRead($id)
    {
        Notification::where('user_id', Auth::id())
            ->findOrFail($id)
            ->update(['read' => now()]);

        return response()->json([
            'message' => 'Notification marked as read.',
            'unread_notifications_count' => $this->unreadNotificationsCount((int) Auth::id()),
        ]);
    }

    public function markAllAsRead()
    {
        Notification::where('user_id', Auth::id())
            ->unread()
            ->update(['read' => now()]);

        return response()->json([
            'message' => 'Notifications marked as read.',
            'unread_notifications_count' => $this->unreadNotificationsCount((int) Auth::id()),
        ]);
    }

    private function unreadNotificationsCount(int $userId): int
    {
        return Notification::where('user_id', $userId)
            ->unread()
            ->count();
    }
}
