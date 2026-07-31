<?php

namespace App\Services\Calls;

use App\Models\CallSession;
use App\Models\Conversation;

class FindActiveCallSessionService
{
    public const ACTIVE_STATUSES = ['ringing', 'accepted', 'active'];

    public function __construct(
        private readonly CallSessionExpiryService $expiry,
    ) {}

    public function findForConversation(Conversation $conversation): ?CallSession
    {
        $this->expiry->expireStaleCalls();

        return $this->activeCallForConversation($conversation)->first();
    }

    public function activeCallForConversation(Conversation $conversation)
    {
        return CallSession::with(['startedBy', 'conversation.property'])
            ->where('conversation_id', $conversation->id)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->latest('started_at');
    }

    public function activeCallForUsers(array $userIds)
    {
        return CallSession::whereIn('status', self::ACTIVE_STATUSES)
            ->whereHas('conversation', function ($query) use ($userIds) {
                $query->whereIn('user_one_id', $userIds)
                    ->orWhereIn('user_two_id', $userIds);
            });
    }
}
