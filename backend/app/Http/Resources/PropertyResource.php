<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PropertyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $payload = $this->resource->toArray();

        unset($payload['ranking_score'], $payload['public_rating']);

        return $payload;
    }
}
