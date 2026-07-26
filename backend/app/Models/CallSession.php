<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CallSession extends Model
{
    protected $fillable = [
        'conversation_id',
        'started_by_id',
        'provider',
        'domain',
        'room_name',
        'status',
        'started_at',
        'ended_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function startedBy()
    {
        return $this->belongsTo(User::class, 'started_by_id');
    }
}
