<?php

namespace App\Services\Calls;

use App\Models\CallSession;
use Illuminate\Http\Exceptions\HttpResponseException;

class EndCallSessionService
{
    public function __construct(
        private readonly CallSessionGuard $guard,
        private readonly CallSessionExpiryService $expiry,
    ) {}

    public function end(CallSession $callSession, int $userId): CallSession
    {
        $callSession->load('conversation');
        $this->expiry->expireCallIfStale($callSession);
        $callSession->refresh();

        $this->guard->assertCallParticipant($callSession, $userId);

        if (! in_array($callSession->status, FindActiveCallSessionService::ACTIVE_STATUSES, true)) {
            $this->conflict('This call has already finished.');
        }

        $callSession->update([
            'status' => 'ended',
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
