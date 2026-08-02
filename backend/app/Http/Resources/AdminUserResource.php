<?php

namespace App\Http\Resources;

use App\Services\Analytics\DemoAnalyticsDataService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
            'registered_country_code' => $this->registered_country_code,
            'registered_country_name' => $this->registered_country_name,
            'registered_region_name' => $this->registered_region_name,
            'registered_city_name' => $this->registered_city_name,
            'last_seen_country_code' => $this->last_seen_country_code,
            'last_seen_country_name' => $this->last_seen_country_name,
            'last_seen_region_name' => $this->last_seen_region_name,
            'last_seen_city_name' => $this->last_seen_city_name,
            'last_seen_at' => $this->last_seen_at?->toJSON(),
            'is_demo_user' => str($this->email)->is('geo.demo.*@luxurrstay.test'),
            'counts' => [
                'properties_count' => (int) ($this->properties_count ?? 0),
                'bookings_count' => (int) ($this->bookings_count ?? 0),
                'reviews_count' => (int) ($this->reviews_count ?? 0),
                'reports_count' => (int) ($this->reports_count ?? 0),
                'analytics_events_count' => (int) ($this->analytics_events_count ?? 0),
            ],
        ];
    }
}
