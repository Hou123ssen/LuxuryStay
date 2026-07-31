<?php

namespace App\Services\Calls;

use App\Models\CallSession;
use App\Models\Conversation;
use Illuminate\Http\Exceptions\HttpResponseException;

class CallSessionGuard
{
    public function assertConversationParticipant(Conversation $conversation, int $userId): void
    {
        if (! $this->userParticipatesIn($conversation, $userId)) {
            $this->forbidden('This action is unauthorized.');
        }
    }

    public function assertCallParticipant(CallSession $callSession, int $userId): void
    {
        $callSession->loadMissing('conversation');
        $this->assertConversationParticipant($callSession->conversation, $userId);
    }

    public function assertRecipient(CallSession $callSession, int $userId, string $message): void
    {
        if ((int) $callSession->started_by_id === $userId) {
            $this->forbidden($message);
        }
    }

    public function userParticipatesIn(Conversation $conversation, int $userId): bool
    {
        return (int) $conversation->user_one_id === $userId
            || (int) $conversation->user_two_id === $userId;
    }

    private function forbidden(string $message): never
    {
        throw new HttpResponseException(response()->json([
            'message' => $message,
        ], 403));
    }
}
