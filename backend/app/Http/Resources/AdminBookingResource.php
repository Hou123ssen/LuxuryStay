<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminBookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'nights' => $this->nights(),
            'total_price' => $this->total_price === null ? null : (float) $this->total_price,
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
            'guest' => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
                'email' => $this->user?->email,
                'role' => $this->user?->role,
            ],
            'property' => [
                'id' => $this->property?->id,
                'title' => $this->property?->title,
                'city' => $this->property?->city,
            ],
            'owner' => [
                'id' => $this->property?->user?->id,
                'name' => $this->property?->user?->name,
                'email' => $this->property?->user?->email,
                'role' => $this->property?->user?->role,
            ],
            'signals' => [
                'has_review' => (int) ($this->reviews_count ?? 0) > 0,
                'reviews_count' => (int) ($this->reviews_count ?? 0),
                'reports_count' => (int) ($this->reports_count ?? 0),
            ],
        ];
    }

    private function nights(): ?int
    {
        if (! $this->start_date || ! $this->end_date) {
            return null;
        }

        $nights = Carbon::parse($this->start_date)->startOfDay()
            ->diffInDays(Carbon::parse($this->end_date)->startOfDay(), false);

        return $nights < 0 ? null : $nights;
    }
}
