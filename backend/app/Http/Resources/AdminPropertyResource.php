<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class AdminPropertyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description_excerpt' => $this->description ? Str::limit($this->description, 160) : null,
            'type' => $this->type,
            'price_per_night' => $this->price_per_night === null ? null : (float) $this->price_per_night,
            'city' => $this->city,
            'status' => $this->getAttribute('status'),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
            'owner' => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
                'email' => $this->user?->email,
                'role' => $this->user?->role,
            ],
            'counts' => [
                'bookings_count' => (int) ($this->bookings_count ?? 0),
                'reviews_count' => (int) ($this->reviews_count ?? 0),
                'reports_count' => (int) ($this->reports_count ?? 0),
                'images_count' => (int) ($this->images_count ?? 0),
            ],
            'rating' => [
                'average_rating' => $this->average_rating,
                'reviews_count' => (int) ($this->reviews_count ?? 0),
                'rating_state' => $this->rating_state,
                'rating_label' => $this->rating_label,
                'trust_badge' => $this->trust_badge,
                'trust_label' => $this->trust_label,
            ],
            'owner_reliability' => $this->owner_reliability,
        ];
    }
}
