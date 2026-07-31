<?php

namespace App\Services\Conversations;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class MessageService
{
    public function __construct(
        private readonly ConversationGuard $conversationGuard
    ) {
    }

    public function messagesForConversation(int $conversationId, int $userId): Collection
    {
        $conversation = Conversation::findOrFail($conversationId);

        $this->conversationGuard->authorizeParticipant($conversation, $userId);

        return Message::with('sender')
            ->where('conversation_id', $conversation->id)
            ->oldest()
            ->get();
    }

    public function send(array $validated, int $userId): Message
    {
        $body = trim($validated['body']);

        if ($body === '') {
            throw ValidationException::withMessages([
                'body' => ['The body field is required.'],
            ]);
        }

        $conversation = Conversation::findOrFail($validated['conversation_id']);

        $this->conversationGuard->authorizeParticipant($conversation, $userId);

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $userId,
            'message' => $body,
        ]);

        $conversation->touch();

        return $message->load('sender');
    }
}
