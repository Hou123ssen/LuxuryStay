<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function index()
    {
        $notifications = Notification::where('user_id', Auth::id())
            ->latest()
            ->get()
            ->map(function ($n) {
                // فك تشفير الـ message إذا كان JSON
                $decoded = json_decode($n->message, true);
                return [
                    'id'         => $n->id,
                    'read'       => $n->read !== null,
                    'created_at' => $n->created_at,
                    // إذا JSON → استخدم text، وإلا استخدم message مباشرة
                    'message'    => $decoded['text']    ?? $n->message,
                    'type'       => $decoded['type']    ?? 'general',
                    'booking_id'  => $decoded['booking_id']  ?? null,
                    'property_id' => $decoded['property_id'] ?? null,
                    'property_title' => $decoded['property_title'] ?? $decoded['property'] ?? null,
                    'guest_name' => $decoded['guest_name'] ?? $decoded['booker_name'] ?? null,
                    'check_in' => $decoded['check_in'] ?? $decoded['start_date'] ?? null,
                    'check_out' => $decoded['check_out'] ?? $decoded['end_date'] ?? null,
                    'booker_id'   => $decoded['booker_id']   ?? null,
                ];
            });

        return response()->json(['data' => $notifications]);
    }

    public function markAsRead($id)
    {
        Notification::where('user_id', Auth::id())
            ->findOrFail($id)
            ->update(['read' => now()]);

        return response()->json(['message' => 'Marked as read']);
    }

    public function markAllAsRead()
    {
        Notification::where('user_id', Auth::id())
            ->whereNull('read')
            ->update(['read' => now()]);

        return response()->json(['message' => 'All marked as read']);
    }
}
