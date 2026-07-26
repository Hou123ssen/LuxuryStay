<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CallSession;
use App\Models\Conversation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class CallSessionController extends Controller
{
    public function store(Conversation $conversation)
    {
        $userId = (int) Auth::id();

        if (! $this->userParticipatesIn($conversation, $userId)) {
            return response()->json([
                'message' => 'This action is unauthorized.',
            ], 403);
        }

        $callSession = CallSession::where('conversation_id', $conversation->id)
            ->where('status', 'active')
            ->latest('started_at')
            ->first();

        if (! $callSession) {
            $callSession = CallSession::create([
                'conversation_id' => $conversation->id,
                'started_by_id' => $userId,
                'provider' => config('services.jitsi.provider'),
                'domain' => config('services.jitsi.domain'),
                'room_name' => $this->generateRoomName(),
                'status' => 'active',
                'started_at' => now(),
            ]);
        }

        return response()->json([
            'message' => 'Call session ready.',
            'data' => $this->callSessionPayload($callSession),
        ]);
    }

    public function active(Conversation $conversation)
    {
        $userId = (int) Auth::id();

        if (! $this->userParticipatesIn($conversation, $userId)) {
            return response()->json([
                'message' => 'This action is unauthorized.',
            ], 403);
        }

        $callSession = CallSession::where('conversation_id', $conversation->id)
            ->where('status', 'active')
            ->latest('started_at')
            ->first();

        return response()->json([
            'data' => $callSession ? $this->callSessionPayload($callSession) : null,
        ]);
    }

    public function end(CallSession $callSession)
    {
        $userId = (int) Auth::id();
        $callSession->load('conversation');

        if (! $this->userParticipatesIn($callSession->conversation, $userId)) {
            return response()->json([
                'message' => 'This action is unauthorized.',
            ], 403);
        }

        if ($callSession->status !== 'ended') {
            $callSession->update([
                'status' => 'ended',
                'ended_at' => now(),
            ]);
        }

        return response()->json([
            'message' => 'Call session ended.',
            'data' => $this->callSessionPayload($callSession->refresh()),
        ]);
    }

    private function userParticipatesIn(Conversation $conversation, int $userId): bool
    {
        return (int) $conversation->user_one_id === $userId
            || (int) $conversation->user_two_id === $userId;
    }

    private function generateRoomName(): string
    {
        do {
            $roomName = 'luxurrstay-'.Str::lower(Str::random(40));
        } while (CallSession::where('room_name', $roomName)->exists());

        return $roomName;
    }

    private function callSessionPayload(CallSession $callSession): array
    {
        return [
            'id' => $callSession->id,
            'conversation_id' => $callSession->conversation_id,
            'provider' => $callSession->provider,
            'domain' => $callSession->domain,
            'script_url' => config('services.jitsi.script_url'),
            'room_name' => $callSession->room_name,
            'audio_only' => true,
            'status' => $callSession->status,
            'started_by_id' => $callSession->started_by_id,
            'started_at' => $callSession->started_at?->toISOString(),
            'ended_at' => $callSession->ended_at?->toISOString(),
        ];
    }
}
