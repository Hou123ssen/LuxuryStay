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
                    'read'       => $n->read,
                    'created_at' => $n->created_at,
                    // إذا JSON → استخدم text، وإلا استخدم message مباشرة
                    'message'    => $decoded['text']    ?? $n->message,
                    'type'       => $decoded['type']    ?? 'general',
                    'booking_id'  => $decoded['booking_id']  ?? null,
                    'booker_id'   => $decoded['booker_id']   ?? null,
                ];
            });

        return response()->json(['data' => $notifications]);
    }

    public function markAsRead($id)
    {
        Notification::where('user_id', Auth::id())
            ->findOrFail($id)
            ->update(['read' => true]);

        return response()->json(['message' => 'Marked as read']);
    }

    public function markAllAsRead()
    {
        Notification::where('user_id', Auth::id())
            ->where('read', false)
            ->update(['read' => true]);

        return response()->json(['message' => 'All marked as read']);
    }
}