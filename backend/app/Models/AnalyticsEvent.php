<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalyticsEvent extends Model
{
    public const TYPE_USER_REGISTERED = 'user_registered';
    public const TYPE_USER_LOGGED_IN = 'user_logged_in';

    protected $fillable = [
        'user_id',
        'event_type',
        'country_code',
        'country_name',
        'country_source',
        'region_name',
        'city_name',
        'ip_hash',
        'user_agent_hash',
        'metadata',
        'occurred_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'occurred_at' => 'datetime',
    ];

    protected $hidden = [
        'ip_hash',
        'user_agent_hash',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
