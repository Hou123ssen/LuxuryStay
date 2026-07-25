<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ConversationController extends Controller
{
    // ── GET /api/conversations ────────────────────────────────────────────────
    public function index()
    {
        $userId = (int) Auth::id();

        $conversations = Conversation::with(['userOne', 'userTwo', 'lastMessage'])
            ->where('user_one_id', $userId)
            ->orWhere('user_two_id', $userId)
            ->latest('updated_at')
            ->get()
            ->map(function ($conv) use ($userId) {
                // الشخص الآخر في المحادثة
                $other = (int) $conv->user_one_id === $userId
                    ? $conv->userTwo
                    : $conv->userOne;

                return [
                    'id'           => $conv->id,
                    'user_one_id'  => $conv->user_one_id,
                    'user_two_id'  => $conv->user_two_id,
                    'other_user'   => $other,
                    'last_message' => $conv->lastMessage,
                    'updated_at'   => $conv->updated_at,
                ];
            });

        return response()->json(['data' => $conversations]);
    }

    // ── POST /api/conversations ───────────────────────────────────────────────
    public function store(Request $request)
    {
        $request->validate([
            // يقبل other_user_id مباشرة أو property_id للبحث عن المالك
            'other_user_id' => 'required_without:property_id|integer|exists:users,id',
            'property_id'   => 'required_without:other_user_id|integer|exists:properties,id',
        ]);

        $userId = (int) Auth::id();

        // ── تحديد الشخص الآخر ────────────────────────────────────────────────
        if ($request->property_id) {
            $property    = Property::findOrFail($request->property_id);
            $otherUserId = (int) $property->user_id;
        } else {
            $otherUserId = (int) $request->other_user_id;
        }

        // ── لا تبدأ محادثة مع نفسك ───────────────────────────────────────────
        if ($otherUserId === $userId) {
            return response()->json([
                'message' => 'You cannot start a conversation with yourself.',
            ], 422);
        }

        // ── ابحث عن محادثة موجودة ────────────────────────────────────────────
        $existing = Conversation::where(function ($q) use ($userId, $otherUserId) {
            $q->where('user_one_id', $userId)
                ->where('user_two_id', $otherUserId);
        })->orWhere(function ($q) use ($userId, $otherUserId) {
            $q->where('user_one_id', $otherUserId)
                ->where('user_two_id', $userId);
        })->first();

        if ($existing) {
            return response()->json($existing, 200); // ← محادثة موجودة
        }

        // ── أنشئ محادثة جديدة ────────────────────────────────────────────────
        $conversation = Conversation::create([
            'user_one_id' => $userId,
            'user_two_id' => $otherUserId,
        ]);

        return response()->json($conversation, 201); // ← محادثة جديدة
    }

    // ── GET /api/messages/{conversationId} ───────────────────────────────────
    public function messages($conversationId)
    {
        $userId = (int) Auth::id();

        $conversation = Conversation::where('id', $conversationId)
            ->where(function ($q) use ($userId) {
                $q->where('user_one_id', $userId)
                    ->orWhere('user_two_id', $userId);
            })->firstOrFail();

        $messages = Message::with('sender')
            ->where('conversation_id', $conversation->id)
            ->oldest()
            ->get();

        return response()->json(['data' => $messages]);
    }

    // ── POST /api/messages ────────────────────────────────────────────────────
    public function sendMessage(Request $request)
    {

        try {
            $request->validate([
                'conversation_id' => 'required|integer|exists:conversations,id',
                'body'            => 'required|string|max:2000',
            ]);

            $userId = (int) Auth::id();

            // تحقق أن المستخدم طرف في المحادثة
            $conversation = Conversation::where('id', $request->conversation_id)
                ->where(function ($q) use ($userId) {
                    $q->where('user_one_id', $userId)
                        ->orWhere('user_two_id', $userId);
                })->firstOrFail();

            $message = Message::create([
                'conversation_id' => $conversation->id,
                'sender_id'       => $userId,
                'message'         => $request->body,
            ]);

            // تحديث وقت المحادثة
            $conversation->touch();

            return response()->json($message->load('sender'), 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'line'  => $e->getLine(),
            ], 500);
        }
    }
}
