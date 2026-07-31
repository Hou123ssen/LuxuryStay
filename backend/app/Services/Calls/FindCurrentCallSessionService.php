<?php

namespace App\Services\Calls;

use App\Models\CallSession;

class FindCurrentCallSessionService
{
    private const TERMINAL_STATUSES = ['declined', 'ended', 'missed'];

    public function __construct(
        private readonly CallSessionExpiryService $expiry,
    ) {}

    public function findForUser(int $userId): ?CallSession
    {
        $this->expiry->expireStaleCalls();

        return CallSession::with(['startedBy', 'conversation.property'])
            ->whereHas('conversation', function ($query) use ($userId) {
                $query->where('user_one_id', $userId)
                    ->orWhere('user_two_id', $userId);
            })
            ->whereIn('status', array_merge(FindActiveCallSessionService::ACTIVE_STATUSES, self::TERMINAL_STATUSES))
            ->latest('updated_at')
            ->first();
    }
}
