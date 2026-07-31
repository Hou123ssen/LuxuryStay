<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class ReviewModerationLog extends Model
{
    public const ACTION_CREATED = 'created';
    public const ACTION_AUTO_PUBLISHED = 'auto_published';
    public const ACTION_AUTO_FLAGGED = 'auto_flagged';
    public const ACTION_MODERATOR_PUBLISHED = 'moderator_published';
    public const ACTION_MODERATOR_REJECTED = 'moderator_rejected';
    public const ACTION_STATUS_CHANGED = 'status_changed';

    public $timestamps = false;

    protected $fillable = [
        'review_id',
        'actor_user_id',
        'action',
        'reason',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('Review moderation logs are immutable.');
        });

        static::deleting(function () {
            throw new LogicException('Review moderation logs are immutable.');
        });
    }

    public function review()
    {
        return $this->belongsTo(Review::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
