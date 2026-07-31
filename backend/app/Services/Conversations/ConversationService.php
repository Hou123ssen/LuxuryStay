<?php

namespace App\Services\Conversations;

use App\Models\Conversation;
use App\Models\Property;
use Illuminate\Http\Exceptions\HttpResponseException;

class ConversationService
{
    public function __construct(
        private readonly ConversationReadService $conversationReadService
    ) {
    }

    public function createOrReuse(array $validated, int $userId): array
    {
        $propertyId = null;

        if (isset($validated['property_id'])) {
            $property = Property::findOrFail($validated['property_id']);
            $propertyId = $property->id;
            $otherUserId = (int) $property->user_id;

            if ($otherUserId === $userId) {
                throw new HttpResponseException(response()->json([
                    'message' => 'You cannot start a conversation about your own property.',
                ], 422));
            }
        } else {
            $otherUserId = (int) $validated['other_user_id'];
        }

        if ($otherUserId === $userId) {
            throw new HttpResponseException(response()->json([
                'message' => 'You cannot start a conversation with yourself.',
            ], 422));
        }

        $existing = $this->findConversationForUsers($userId, $otherUserId, $propertyId);

        if ($existing) {
            $existing->loadCount($this->conversationReadService->unreadMessageCountWithCount($userId));

            return [
                'conversation' => $existing->load(['property', 'userOne', 'userTwo', 'lastMessage']),
                'created' => false,
            ];
        }

        $conversation = Conversation::create([
            'property_id' => $propertyId,
            'user_one_id' => $userId,
            'user_two_id' => $otherUserId,
        ])->load(['property', 'userOne', 'userTwo', 'lastMessage']);

        return [
            'conversation' => $conversation,
            'created' => true,
        ];
    }

    public function findConversationForUsers(int $userId, int $otherUserId, ?int $propertyId = null): ?Conversation
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
}
