<?php

namespace App\Services\Calls;

use App\Models\CallSession;
use App\Models\Conversation;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;

class CreateCallSessionService
{
    private const CALL_PARTICIPANT_BUSY_CODE = 'CALL_PARTICIPANT_BUSY';
    private const CALL_PARTICIPANT_BUSY_MESSAGE = 'This user is currently busy on another call. Please try again later.';

    public function __construct(
        private readonly CallSessionGuard $guard,
        private readonly CallSessionExpiryService $expiry,
        private readonly FindActiveCallSessionService $activeCalls,
    ) {}

    public function createOrReuse(Conversation $conversation, int $userId): CallSession
    {
        $this->expiry->expireStaleCalls();
        $this->guard->assertConversationParticipant($conversation, $userId);

        $callSession = $this->activeCalls->activeCallForConversation($conversation)->first();
        if ($callSession) {
            return $callSession;
        }

        $participantIds = [(int) $conversation->user_one_id, (int) $conversation->user_two_id];
        if ($this->activeCalls->activeCallForUsers($participantIds)->exists()) {
            $this->conflict(self::CALL_PARTICIPANT_BUSY_MESSAGE, self::CALL_PARTICIPANT_BUSY_CODE);
        }

        return CallSession::create([
            'conversation_id' => $conversation->id,
            'started_by_id' => $userId,
            'provider' => config('services.jitsi.provider'),
            'domain' => config('services.jitsi.domain'),
            'room_name' => $this->generateRoomName(),
            'status' => 'ringing',
            'started_at' => now(),
        ]);
    }

    private function generateRoomName(): string
    {
        do {
            $roomName = 'luxurrstay-'.Str::lower(Str::random(40));
        } while (CallSession::where('room_name', $roomName)->exists());

        return $roomName;
    }

    private function conflict(string $message, ?string $code = null): never
    {
        $payload = ['message' => $message];

        if ($code !== null) {
            $payload['code'] = $code;
        }

        throw new HttpResponseException(response()->json($payload, 409));
    }
}
