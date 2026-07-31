<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'property_id' => $this->property_id,
            'booking_id' => $this->booking_id,
            'rating' => $this->rating,
            'comment' => $this->comment,
            'status' => $this->status,
            'published_at' => $this->published_at,
            'moderated_at' => $this->moderated_at,
            'moderated_by' => $this->moderated_by,
            'risk_score' => $this->risk_score,
            'risk_reasons' => $this->risk_reasons ?? [],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'user' => $this->whenLoaded('user', function () {
                return $this->user ? [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                ] : null;
            }),
            'property' => $this->whenLoaded('property', function () {
                return $this->property ? [
                    'id' => $this->property->id,
                    'title' => $this->property->title,
                ] : null;
            }),
            'booking' => $this->whenLoaded('booking', function () {
                return $this->booking ? [
                    'id' => $this->booking->id,
                    'start_date' => $this->booking->start_date,
                    'end_date' => $this->booking->end_date,
                    'status' => $this->booking->status,
                ] : null;
            }),
        ];
    }
}
