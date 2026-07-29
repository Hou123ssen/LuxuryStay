<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\ConversationRead;
use App\Models\Message;
use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ConversationController extends Controller
{
    public function index()
    {
        $userId = (int) Auth::id();

        $conversations = $this->withUnreadMessageCount(
            Conversation::with(['property', 'userOne', 'userTwo', 'lastMessage']),
            $userId
        )
            ->where(function ($query) use ($userId) {
                $query->where('user_one_id', $userId)
                    ->orWhere('user_two_id', $userId);
            })
            ->latest('updated_at')
            ->get()
            ->map(function ($conversation) use ($userId) {
                $other = (int) $conversation->user_one_id === $userId
                    ? $conversation->userTwo
                    : $conversation->userOne;

                return $this->conversationPayload($conversation, $other);
            });

        return response()->json(['data' => $conversations]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'other_user_id' => 'required_without:property_id|integer|exists:users,id',
            'property_id' => 'required_without:other_user_id|integer|exists:properties,id',
            'user_one_id' => 'prohibited',
            'user_two_id' => 'prohibited',
            'sender_id' => 'prohibited',
        ]);

        $userId = (int) Auth::id();

        if (isset($validated['property_id'])) {
            $property = Property::findOrFail($validated['property_id']);
            $otherUserId = (int) $property->user_id;

            if ($otherUserId === $userId) {
                return response()->json([
                    'message' => 'You cannot start a conversation about your own property.',
                ], 422);
            }

            $existing = $this->findConversationForUsers($userId, $otherUserId, $property->id);

            if ($existing) {
                $existing->loadCount($this->unreadMessageCountWithCount($userId));

                return response()->json($this->conversationPayload(
                    $existing->load(['property', 'userOne', 'userTwo', 'lastMessage']),
                    $this->otherUserFor($existing, $userId)
                ));
            }

            $conversation = Conversation::create([
                'property_id' => $property->id,
                'user_one_id' => $userId,
                'user_two_id' => $otherUserId,
            ])->load(['property', 'userOne', 'userTwo', 'lastMessage']);

            return response()->json($this->conversationPayload(
                $conversation,
                $this->otherUserFor($conversation, $userId)
            ), 201);
        } else {
            $otherUserId = (int) $validated['other_user_id'];
        }

        if ($otherUserId === $userId) {
            return response()->json([
                'message' => 'You cannot start a conversation with yourself.',
            ], 422);
        }

        $existing = $this->findConversationForUsers($userId, $otherUserId);

        if ($existing) {
            $existing->loadCount($this->unreadMessageCountWithCount($userId));

            return response()->json($this->conversationPayload(
                $existing->load(['property', 'userOne', 'userTwo', 'lastMessage']),
                $this->otherUserFor($existing, $userId)
            ));
        }

        $conversation = Conversation::create([
            'user_one_id' => $userId,
            'user_two_id' => $otherUserId,
        ])->load(['property', 'userOne', 'userTwo', 'lastMessage']);

        return response()->json($this->conversationPayload(
            $conversation,
            $this->otherUserFor($conversation, $userId)
        ), 201);
    }

    public function messages($conversationId)
    {
        $userId = (int) Auth::id();
        $conversation = Conversation::findOrFail($conversationId);

        if (! $this->userParticipatesIn($conversation, $userId)) {
            return response()->json([
                'message' => 'This action is unauthorized.',
            ], 403);
        }

        $messages = Message::with('sender')
            ->where('conversation_id', $conversation->id)
            ->oldest()
            ->get();

        return response()->json(['data' => $messages]);
    }

    public function sendMessage(Request $request)
    {
        $validated = $request->validate([
            'conversation_id' => 'required|integer|exists:conversations,id',
            'body' => 'required|string|max:2000',
            'sender_id' => 'prohibited',
        ]);

        $body = trim($validated['body']);

        if ($body === '') {
            throw ValidationException::withMessages([
                'body' => ['The body field is required.'],
            ]);
        }

        $userId = (int) Auth::id();
        $conversation = Conversation::findOrFail($validated['conversation_id']);

        if (! $this->userParticipatesIn($conversation, $userId)) {
            return response()->json([
                'message' => 'This action is unauthorized.',
            ], 403);
        }

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $userId,
            'message' => $body,
        ]);

        $conversation->touch();

        return response()->json($message->load('sender'), 201);
    }

    public function markAsRead(Conversation $conversation)
    {
        $userId = (int) Auth::id();

        if (! $this->userParticipatesIn($conversation, $userId)) {
            return response()->json([
                'message' => 'This action is unauthorized.',
            ], 403);
        }

        ConversationRead::updateOrCreate(
            [
                'conversation_id' => $conversation->id,
                'user_id' => $userId,
            ],
            ['last_read_at' => now()]
        );

        return response()->json([
            'message' => 'Conversation marked as read.',
            'data' => [
                'conversation_id' => $conversation->id,
                'unread_message_count' => 0,
            ],
        ]);
    }

    private function userParticipatesIn(Conversation $conversation, int $userId): bool
    {
        return (int) $conversation->user_one_id === $userId
            || (int) $conversation->user_two_id === $userId;
    }

    private function findConversationForUsers(int $userId, int $otherUserId, ?int $propertyId = null): ?Conversation
    {
        return Conversation::where('property_id', $propertyId)
            ->where(function ($query) use ($userId, $otherUserId) {
                $query->where(function ($nested) use ($userId, $otherUserId) {
                    $nested->where('user_one_id', $userId)
                        ->where('user_two_id', $otherUserId);
                })->orWhere(function ($nested) use ($userId, $otherUserId) {
                    $nested->where('user_one_id', $otherUserId)
                        ->where('user_two_id', $userId);
                });
            })
            ->first();
    }

    private function otherUserFor(Conversation $conversation, int $userId)
    {
        return (int) $conversation->user_one_id === $userId
            ? $conversation->userTwo
            : $conversation->userOne;
    }

    private function conversationPayload(Conversation $conversation, $otherUser): array
    {
        return [
            'id' => $conversation->id,
            'property_id' => $conversation->property_id,
            'property' => $conversation->property ? [
                'id' => $conversation->property->id,
                'title' => $conversation->property->title,
                'city' => $conversation->property->city,
            ] : null,
            'user_one_id' => $conversation->user_one_id,
            'user_two_id' => $conversation->user_two_id,
            'other_user' => $otherUser,
            'last_message' => $conversation->lastMessage,
            'unread_message_count' => (int) ($conversation->unread_message_count ?? 0),
            'updated_at' => $conversation->updated_at,
        ];
    }

    private function withUnreadMessageCount($query, int $userId)
    {
        return $query->withCount($this->unreadMessageCountWithCount($userId));
    }

    private function unreadMessageCountWithCount(int $userId): array
    {
        return [
            'messages as unread_message_count' => function ($query) use ($userId) {
                $query
                    ->where('sender_id', '<>', $userId)
                    ->where(function ($query) use ($userId) {
                        $query
                            ->whereNotExists(function ($subquery) use ($userId) {
                                $subquery
                                    ->selectRaw('1')
                                    ->from('conversation_reads')
                                    ->whereColumn('conversation_reads.conversation_id', 'messages.conversation_id')
                                    ->where('conversation_reads.user_id', $userId)
                                    ->whereNotNull('conversation_reads.last_read_at');
                            })
                            ->orWhere('messages.created_at', '>', function ($subquery) use ($userId) {
                                $subquery
                                    ->select('last_read_at')
                                    ->from('conversation_reads')
                                    ->whereColumn('conversation_reads.conversation_id', 'messages.conversation_id')
                                    ->where('conversation_reads.user_id', $userId)
                                    ->limit(1);
                            });
                    });
            },
        ];
    }
}
