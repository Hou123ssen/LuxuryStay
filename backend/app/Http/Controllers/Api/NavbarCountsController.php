<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Notification;
use Illuminate\Support\Facades\Auth;

class NavbarCountsController extends Controller
{
    public function __invoke()
    {
        $userId = (int) Auth::id();

        $unreadMessagesCount = Message::query()
            ->join('conversations', 'messages.conversation_id', '=', 'conversations.id')
            ->leftJoin('conversation_reads', function ($join) use ($userId) {
                $join
                    ->on('conversation_reads.conversation_id', '=', 'messages.conversation_id')
                    ->where('conversation_reads.user_id', $userId);
            })
            ->where('messages.sender_id', '<>', $userId)
            ->where(function ($query) use ($userId) {
                $query->where('conversations.user_one_id', $userId)
                    ->orWhere('conversations.user_two_id', $userId);
            })
            ->where(function ($query) {
                $query->whereNull('conversation_reads.last_read_at')
                    ->orWhereColumn('messages.created_at', '>', 'conversation_reads.last_read_at');
            })
            ->count('messages.id');

        $unreadNotificationsCount = Notification::where('user_id', $userId)
            ->unread()
            ->count();

        return response()->json([
            'data' => [
                'unread_messages_count' => $unreadMessagesCount,
                'unread_notifications_count' => $unreadNotificationsCount,
            ],
        ]);
    }
}
