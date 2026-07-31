<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CallSessionResource;
use App\Models\CallSession;
use App\Models\Conversation;
use App\Services\Calls\AcceptCallSessionService;
use App\Services\Calls\CallSessionGuard;
use App\Services\Calls\CreateCallSessionService;
use App\Services\Calls\DeclineCallSessionService;
use App\Services\Calls\EndCallSessionService;
use App\Services\Calls\FindActiveCallSessionService;
use App\Services\Calls\FindCurrentCallSessionService;
use App\Services\Calls\FindIncomingCallSessionService;
use Illuminate\Support\Facades\Auth;

class CallSessionController extends Controller
{
    public function store(Conversation $conversation, CreateCallSessionService $calls)
    {
        $callSession = $calls->createOrReuse($conversation, (int) Auth::id());

        return response()->json([
            'message' => 'Call session ready.',
            'data' => new CallSessionResource($callSession),
        ]);
    }

    public function active(
        Conversation $conversation,
        CallSessionGuard $guard,
        FindActiveCallSessionService $calls,
    ) {
        $userId = (int) Auth::id();
        $guard->assertConversationParticipant($conversation, $userId);
        $callSession = $calls->findForConversation($conversation);

        return response()->json([
            'data' => $callSession ? new CallSessionResource($callSession) : null,
        ]);
    }

    public function incoming(FindIncomingCallSessionService $calls)
    {
        $callSession = $calls->findForUser((int) Auth::id());

        return response()->json([
            'data' => $callSession ? new CallSessionResource($callSession) : null,
        ]);
    }

    public function current(FindCurrentCallSessionService $calls)
    {
        $callSession = $calls->findForUser((int) Auth::id());

        return response()->json([
            'data' => $callSession ? new CallSessionResource($callSession) : null,
        ]);
    }

    public function accept(CallSession $callSession, AcceptCallSessionService $calls)
    {
        $callSession = $calls->accept($callSession, (int) Auth::id());

        return response()->json([
            'message' => 'Call session accepted.',
            'data' => new CallSessionResource($callSession),
        ]);
    }

    public function decline(CallSession $callSession, DeclineCallSessionService $calls)
    {
        $callSession = $calls->decline($callSession, (int) Auth::id());

        return response()->json([
            'message' => 'Call session declined.',
            'data' => new CallSessionResource($callSession),
        ]);
    }

    public function end(CallSession $callSession, EndCallSessionService $calls)
    {
        $callSession = $calls->end($callSession, (int) Auth::id());

        return response()->json([
            'message' => 'Call session ended.',
            'data' => new CallSessionResource($callSession),
        ]);
    }
}
