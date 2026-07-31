<?php

namespace App\Services\Calls;

use App\Models\CallSession;

class FindIncomingCallSessionService
{
    public function __construct(
        private readonly CallSessionExpiryService $expiry,
    ) {}

    public function findForUser(int $userId): ?CallSession
    {
        $this->expiry->expireStaleCalls();

        return CallSession::with(['startedBy', 'conversation.property'])
            ->where('status', 'ringing')
            ->where('started_by_id', '<>', $userId)
            ->whereHas('conversation', function ($query) use ($userId) {
                $query->where('user_one_id', $userId)
                    ->orWhere('user_two_id', $userId);
            })
            ->latest('started_at')
            ->first();
    }
}
