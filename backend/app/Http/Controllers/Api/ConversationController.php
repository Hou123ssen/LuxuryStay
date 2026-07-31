<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Conversations\SendMessageRequest;
use App\Http\Requests\Conversations\StoreConversationRequest;
use App\Http\Resources\ConversationResource;
use App\Models\Conversation;
use App\Services\Conversations\ConversationGuard;
use App\Services\Conversations\ConversationReadService;
use App\Services\Conversations\ConversationService;
use App\Services\Conversations\MessageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ConversationController extends Controller
{
    public function index(Request $request, ConversationReadService $conversationReadService)
    {
        $userId = (int) Auth::id();

        $conversations = $conversationReadService->withUnreadMessageCount(
            Conversation::with(['property', 'userOne', 'userTwo', 'lastMessage']),
            $userId
        )
            ->where(function ($query) use ($userId) {
                $query->where('user_one_id', $userId)
                    ->orWhere('user_two_id', $userId);
            })
            ->latest('updated_at')
            ->paginate($this->perPage($request, 10))
            ->withQueryString();

        $conversations->getCollection()->transform(function ($conversation) use ($userId) {
            return (new ConversationResource($conversation, $userId))->resolve();
        });

        return $this->paginatedResponse($conversations);
    }

    public function store(StoreConversationRequest $request, ConversationService $conversationService)
    {
        $userId = (int) Auth::id();
        $result = $conversationService->createOrReuse($request->validated(), $userId);

        return response()->json(
            (new ConversationResource($result['conversation'], $userId))->resolve(),
            $result['created'] ? 201 : 200
        );
    }

    public function messages($conversationId, MessageService $messageService)
    {
        return response()->json([
            'data' => $messageService->messagesForConversation((int) $conversationId, (int) Auth::id()),
        ]);
    }

    public function sendMessage(SendMessageRequest $request, MessageService $messageService)
    {
        return response()->json(
            $messageService->send($request->validated(), (int) Auth::id()),
            201
        );
    }

    public function markAsRead(
        Conversation $conversation,
        ConversationGuard $conversationGuard,
        ConversationReadService $conversationReadService
    )
    {
        $userId = (int) Auth::id();

        $conversationGuard->authorizeParticipant($conversation, $userId);

        return response()->json([
            'message' => 'Conversation marked as read.',
            'data' => $conversationReadService->markAsRead($conversation, $userId),
        ]);
    }
}
