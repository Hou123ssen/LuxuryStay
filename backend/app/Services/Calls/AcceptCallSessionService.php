<?php

namespace App\Services\Calls;

use App\Models\CallSession;
use Illuminate\Http\Exceptions\HttpResponseException;

class AcceptCallSessionService
{
    public function __construct(
        private readonly CallSessionGuard $guard,
        private readonly CallSessionExpiryService $expiry,
        private readonly FindActiveCallSessionService $activeCalls,
    ) {}

    public function accept(CallSession $callSession, int $userId): CallSession
    {
        $callSession->load('conversation');
        $this->expiry->expireCallIfStale($callSession);
        $callSession->refresh();

        $this->guard->assertCallParticipant($callSession, $userId);
        $this->guard->assertRecipient($callSession, $userId, 'Only the recipient can accept this call.');

        if ($callSession->status !== 'ringing') {
            $this->conflict('This call can no longer be accepted.');
        }

        if ($this->activeCalls->activeCallForUsers([$userId])
            ->where('call_sessions.id', '<>', $callSession->id)
            ->exists()) {
            $this->conflict('You are already in an active call.');
        }

        $callSession->update(['status' => 'accepted']);

        return $callSession->refresh();
    }

    private function conflict(string $message): never
    {
        throw new HttpResponseException(response()->json([
            'message' => $message,
        ], 409));
    }
}
