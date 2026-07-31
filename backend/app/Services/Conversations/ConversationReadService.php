<?php

namespace App\Services\Conversations;

use App\Models\Conversation;
use App\Models\ConversationRead;

class ConversationReadService
{
    public function withUnreadMessageCount($query, int $userId)
    {
        return $query->withCount($this->unreadMessageCountWithCount($userId));
    }

    public function unreadMessageCountWithCount(int $userId): array
    {
        return [
            'messages as unread_message_count' => function ($query) use ($userId) {
                $query
                    ->where('sender_id', '<>', $userId)
                    ->where(function ($query) use ($userId) {
                        $query
                            ->whereNotExists(function ($subquery) use ($userId) {
                                $subquery
                                    ->selectRaw('1')
                                    ->from('conversation_reads')
                                    ->whereColumn('conversation_reads.conversation_id', 'messages.conversation_id')
                                    ->where('conversation_reads.user_id', $userId)
                                    ->whereNotNull('conversation_reads.last_read_at');
                            })
                            ->orWhere('messages.created_at', '>', function ($subquery) use ($userId) {
                                $subquery
                                    ->select('last_read_at')
                                    ->from('conversation_reads')
                                    ->whereColumn('conversation_reads.conversation_id', 'messages.conversation_id')
                                    ->where('conversation_reads.user_id', $userId)
                                    ->limit(1);
                            });
                    });
            },
        ];
    }

    public function markAsRead(Conversation $conversation, int $userId): array
    {
        ConversationRead::updateOrCreate(
            [
                'conversation_id' => $conversation->id,
                'user_id' => $userId,
            ],
            ['last_read_at' => now()]
        );

        return [
            'conversation_id' => $conversation->id,
            'unread_message_count' => 0,
        ];
    }
}
