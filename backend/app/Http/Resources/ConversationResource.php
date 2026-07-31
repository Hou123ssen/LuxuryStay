<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    public function __construct($resource, private readonly int $userId)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $otherUser = (int) $this->user_one_id === $this->userId
            ? $this->userTwo
            : $this->userOne;

        return [
            'id' => $this->id,
            'property_id' => $this->property_id,
            'property' => $this->property ? [
                'id' => $this->property->id,
                'title' => $this->property->title,
                'city' => $this->property->city,
            ] : null,
            'user_one_id' => $this->user_one_id,
            'user_two_id' => $this->user_two_id,
            'other_user' => $otherUser,
            'last_message' => $this->lastMessage,
            'unread_message_count' => (int) ($this->unread_message_count ?? 0),
            'updated_at' => $this->updated_at,
        ];
    }
}
