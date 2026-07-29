<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CallSession;
use App\Models\Conversation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class CallSessionController extends Controller
{
    private const ACTIVE_STATUSES = ['ringing', 'accepted', 'active'];
    private const JOINABLE_STATUSES = ['accepted', 'active'];
    private const TERMINAL_STATUSES = ['declined', 'ended', 'missed'];
    private const CALL_PARTICIPANT_BUSY_CODE = 'CALL_PARTICIPANT_BUSY';
    private const CALL_PARTICIPANT_BUSY_MESSAGE = 'This user is currently busy on another call. Please try again later.';

    public function store(Conversation $conversation)
    {
        $userId = (int) Auth::id();
        $this->expireStaleCalls();

        if (! $this->userParticipatesIn($conversation, $userId)) {
            return response()->json([
                'message' => 'This action is unauthorized.',
            ], 403);
        }

        $callSession = $this->activeCallForConversation($conversation)->first();

        if ($callSession) {
            return response()->json([
                'message' => 'Call session ready.',
                'data' => $this->callSessionPayload($callSession),
            ]);
        }

        $participantIds = [(int) $conversation->user_one_id, (int) $conversation->user_two_id];
        if ($this->activeCallForUsers($participantIds)->exists()) {
            return $this->conflict(
                self::CALL_PARTICIPANT_BUSY_MESSAGE,
                self::CALL_PARTICIPANT_BUSY_CODE
            );
        }

        $callSession = CallSession::create([
            'conversation_id' => $conversation->id,
            'started_by_id' => $userId,
            'provider' => config('services.jitsi.provider'),
            'domain' => config('services.jitsi.domain'),
            'room_name' => $this->generateRoomName(),
            'status' => 'ringing',
            'started_at' => now(),
        ]);

        return response()->json([
            'message' => 'Call session ready.',
            'data' => $this->callSessionPayload($callSession),
        ]);
    }

    public function active(Conversation $conversation)
    {
        $userId = (int) Auth::id();
        $this->expireStaleCalls();

        if (! $this->userParticipatesIn($conversation, $userId)) {
            return response()->json([
                'message' => 'This action is unauthorized.',
            ], 403);
        }

        $callSession = $this->activeCallForConversation($conversation)->first();

        return response()->json([
            'data' => $callSession ? $this->callSessionPayload($callSession) : null,
        ]);
    }

    public function incoming()
    {
        $userId = (int) Auth::id();
        $this->expireStaleCalls();

        $callSession = CallSession::with(['startedBy', 'conversation.property'])
            ->where('status', 'ringing')
            ->where('started_by_id', '<>', $userId)
            ->whereHas('conversation', function ($query) use ($userId) {
                $query->where('user_one_id', $userId)
                    ->orWhere('user_two_id', $userId);
            })
            ->latest('started_at')
            ->first();

        return response()->json([
            'data' => $callSession ? $this->callSessionPayload($callSession) : null,
        ]);
    }

    public function current()
    {
        $userId = (int) Auth::id();
        $this->expireStaleCalls();

        $callSession = CallSession::with(['startedBy', 'conversation.property'])
            ->whereHas('conversation', function ($query) use ($userId) {
                $query->where('user_one_id', $userId)
                    ->orWhere('user_two_id', $userId);
            })
            ->whereIn('status', array_merge(self::ACTIVE_STATUSES, self::TERMINAL_STATUSES))
            ->latest('updated_at')
            ->first();

        return response()->json([
            'data' => $callSession ? $this->callSessionPayload($callSession) : null,
        ]);
    }

    public function accept(CallSession $callSession)
    {
        $userId = (int) Auth::id();
        $callSession->load('conversation');
        $this->expireCallIfStale($callSession);
        $callSession->refresh();

        if (! $this->userParticipatesIn($callSession->conversation, $userId)) {
            return response()->json([
                'message' => 'This action is unauthorized.',
            ], 403);
        }

        if ((int) $callSession->started_by_id === $userId) {
            return response()->json([
                'message' => 'Only the recipient can accept this call.',
            ], 403);
        }

        if ($callSession->status !== 'ringing') {
            return $this->conflict('This call can no longer be accepted.');
        }

        if ($this->activeCallForUsers([$userId])
            ->where('call_sessions.id', '<>', $callSession->id)
            ->exists()) {
            return $this->conflict('You are already in an active call.');
        }

        $callSession->update(['status' => 'accepted']);

        return response()->json([
            'message' => 'Call session accepted.',
            'data' => $this->callSessionPayload($callSession->refresh()),
        ]);
    }

    public function decline(CallSession $callSession)
    {
        $userId = (int) Auth::id();
        $callSession->load('conversation');
        $this->expireCallIfStale($callSession);
        $callSession->refresh();

        if (! $this->userParticipatesIn($callSession->conversation, $userId)) {
            return response()->json([
                'message' => 'This action is unauthorized.',
            ], 403);
        }

        if ((int) $callSession->started_by_id === $userId) {
            return response()->json([
                'message' => 'Only the recipient can decline this call.',
            ], 403);
        }

        if ($callSession->status !== 'ringing') {
            return $this->conflict('This call can no longer be declined.');
        }

        $callSession->update([
            'status' => 'declined',
            'ended_at' => now(),
        ]);

        return response()->json([
            'message' => 'Call session declined.',
            'data' => $this->callSessionPayload($callSession->refresh()),
        ]);
    }

    public function end(CallSession $callSession)
    {
        $userId = (int) Auth::id();
        $callSession->load('conversation');
        $this->expireCallIfStale($callSession);
        $callSession->refresh();

        if (! $this->userParticipatesIn($callSession->conversation, $userId)) {
            return response()->json([
                'message' => 'This action is unauthorized.',
            ], 403);
        }

        if (! in_array($callSession->status, self::ACTIVE_STATUSES, true)) {
            return $this->conflict('This call has already finished.');
        }

        $callSession->update([
            'status' => 'ended',
            'ended_at' => now(),
        ]);

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
        $callSession->loadMissing([
            'startedBy',
            'conversation.property',
        ]);

        $payload = [
            'id' => $callSession->id,
            'conversation_id' => $callSession->conversation_id,
            'status' => $callSession->status,
            'started_by_id' => $callSession->started_by_id,
            'started_by' => $callSession->startedBy ? [
                'id' => $callSession->startedBy->id,
                'name' => $callSession->startedBy->name,
                'email' => $callSession->startedBy->email,
            ] : null,
            'conversation' => $callSession->conversation ? [
                'id' => $callSession->conversation->id,
                'property_id' => $callSession->conversation->property_id,
                'property' => $callSession->conversation->property ? [
                    'id' => $callSession->conversation->property->id,
                    'title' => $callSession->conversation->property->title,
                    'city' => $callSession->conversation->property->city,
                ] : null,
            ] : null,
            'started_at' => $callSession->started_at?->toISOString(),
            'ended_at' => $callSession->ended_at?->toISOString(),
        ];

        if (in_array($callSession->status, self::JOINABLE_STATUSES, true)) {
            $payload['room_name'] = $callSession->room_name;
        }

        return $payload;
    }

    private function activeCallForConversation(Conversation $conversation)
    {
        return CallSession::with(['startedBy', 'conversation.property'])
            ->where('conversation_id', $conversation->id)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->latest('started_at');
    }

    private function activeCallForUsers(array $userIds)
    {
        return CallSession::whereIn('status', self::ACTIVE_STATUSES)
            ->whereHas('conversation', function ($query) use ($userIds) {
                $query->whereIn('user_one_id', $userIds)
                    ->orWhereIn('user_two_id', $userIds);
            });
    }

    private function expireStaleCalls(): void
    {
        $expiresAt = now()->subSeconds((int) config('calls.ringing_timeout_seconds', 45));

        CallSession::where('status', 'ringing')
            ->where('started_at', '<=', $expiresAt)
            ->update([
                'status' => 'missed',
                'ended_at' => now(),
            ]);
    }

    private function expireCallIfStale(CallSession $callSession): void
    {
        if ($callSession->status !== 'ringing') return;

        $expiresAt = now()->subSeconds((int) config('calls.ringing_timeout_seconds', 45));
        if ($callSession->started_at && $callSession->started_at->lessThanOrEqualTo($expiresAt)) {
            $callSession->update([
                'status' => 'missed',
                'ended_at' => now(),
            ]);
        }
    }

    private function conflict(string $message, ?string $code = null)
    {
        $payload = ['message' => $message];

        if ($code !== null) {
            $payload['code'] = $code;
        }

        return response()->json($payload, 409);
    }
}
