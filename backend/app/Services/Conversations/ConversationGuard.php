<?php

namespace App\Services\Conversations;

use App\Models\Conversation;
use Illuminate\Http\Exceptions\HttpResponseException;

class ConversationGuard
{
    public function userParticipatesIn(Conversation $conversation, int $userId): bool
    {
        return (int) $conversation->user_one_id === $userId
            || (int) $conversation->user_two_id === $userId;
    }

    public function authorizeParticipant(Conversation $conversation, int $userId): void
    {
        if ($this->userParticipatesIn($conversation, $userId)) {
            return;
        }

        throw new HttpResponseException(response()->json([
            'message' => 'This action is unauthorized.',
        ], 403));
    }
}
