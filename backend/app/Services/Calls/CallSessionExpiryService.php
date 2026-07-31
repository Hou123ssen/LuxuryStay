<?php

namespace App\Services\Calls;

use App\Models\CallSession;

class CallSessionExpiryService
{
    public function expireStaleCalls(): void
    {
        $expiresAt = now()->subSeconds((int) config('calls.ringing_timeout_seconds', 45));

        CallSession::where('status', 'ringing')
            ->where('started_at', '<=', $expiresAt)
            ->update([
                'status' => 'missed',
                'ended_at' => now(),
            ]);
    }

    public function expireCallIfStale(CallSession $callSession): void
    {
        if ($callSession->status !== 'ringing') {
            return;
        }

        $expiresAt = now()->subSeconds((int) config('calls.ringing_timeout_seconds', 45));
        if ($callSession->started_at && $callSession->started_at->lessThanOrEqualTo($expiresAt)) {
            $callSession->update([
                'status' => 'missed',
                'ended_at' => now(),
            ]);
        }
    }
}
