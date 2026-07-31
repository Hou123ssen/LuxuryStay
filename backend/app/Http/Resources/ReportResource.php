<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'property_id' => $this->property_id,
            'booking_id' => $this->booking_id,
            'category' => $this->category,
            'status' => $this->status,
            'severity' => $this->severity,
            'description' => $this->description,
            'created_at' => $this->created_at,
        ];
    }
}
