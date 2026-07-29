<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $fillable = [
        'user_id',
        'message',
        'read',
    ];

    protected $casts = [
        'read' => 'datetime',
    ];

    public function scopeUnread($query)
    {
        return $query->where(function ($query) {
            $query->whereNull('read')
                ->orWhere('read', false)
                ->orWhere('read', 0)
                ->orWhere('read', '0');
        });
    }

    public function isRead(): bool
    {
        return ! in_array($this->getRawOriginal('read'), [null, false, 0, '0'], true);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
