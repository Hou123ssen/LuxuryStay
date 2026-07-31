<?php

namespace App\Http\Resources;

use App\Models\CallSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CallSessionResource extends JsonResource
{
    private const JOINABLE_STATUSES = ['accepted', 'active'];

    public function toArray(Request $request): array
    {
        /** @var CallSession $callSession */
        $callSession = $this->resource;

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
}
