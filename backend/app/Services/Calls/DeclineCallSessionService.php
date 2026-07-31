<?php

namespace App\Services\Calls;

use App\Models\CallSession;
use Illuminate\Http\Exceptions\HttpResponseException;

class DeclineCallSessionService
{
    public function __construct(
        private readonly CallSessionGuard $guard,
        private readonly CallSessionExpiryService $expiry,
    ) {}

    public function decline(CallSession $callSession, int $userId): CallSession
    {
        $callSession->load('conversation');
        $this->expiry->expireCallIfStale($callSession);
        $callSession->refresh();

        $this->guard->assertCallParticipant($callSession, $userId);
        $this->guard->assertRecipient($callSession, $userId, 'Only the recipient can decline this call.');

        if ($callSession->status !== 'ringing') {
            $this->conflict('This call can no longer be declined.');
        }

        $callSession->update([
            'status' => 'declined',
            'ended_at' => now(),
        ]);

        return $callSession->refresh();
    }

    private function conflict(string $message): never
    {
        throw new HttpResponseException(response()->json([
            'message' => $message,
        ], 409));
    }
}
