<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reporter_user_id' => $this->reporter_user_id,
            'property_id' => $this->property_id,
            'booking_id' => $this->booking_id,
            'reported_user_id' => $this->reported_user_id,
            'category' => $this->category,
            'description' => $this->description,
            'status' => $this->status,
            'severity' => $this->severity,
            'admin_notes' => $this->admin_notes,
            'reviewed_by_user_id' => $this->reviewed_by_user_id,
            'reviewed_at' => $this->reviewed_at,
            'resolved_at' => $this->resolved_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'property' => $this->whenLoaded('property', function () {
                return $this->property ? [
                    'id' => $this->property->id,
                    'title' => $this->property->title,
                ] : null;
            }),
            'reporter' => $this->whenLoaded('reporter', function () {
                return $this->reporter ? [
                    'id' => $this->reporter->id,
                    'name' => $this->reporter->name,
                ] : null;
            }),
            'reported_user' => $this->whenLoaded('reportedUser', function () {
                return $this->reportedUser ? [
                    'id' => $this->reportedUser->id,
                    'name' => $this->reportedUser->name,
                ] : null;
            }),
        ];
    }
}
