<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_PENDING_REVIEW = 'pending_review';
    public const STATUS_REJECTED = 'rejected';

    /**
     * Get the user that wrote the review.
     */

    protected $fillable = [
        'user_id',
        'property_id',
        'booking_id',
        'rating',
        'comment',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'moderated_at' => 'datetime',
        'risk_reasons' => 'array',
    ];

    protected $hidden = [
        'status',
        'published_at',
        'moderated_at',
        'moderated_by',
        'risk_score',
        'risk_reasons',
        'ip_hash',
        'user_agent_hash',
    ];

    protected static function booted(): void
    {
        static::creating(function (Review $review) {
            if (! $review->status) {
                $review->status = self::STATUS_PUBLISHED;
            }

            if ($review->status === self::STATUS_PUBLISHED && ! $review->published_at) {
                $review->published_at = now();
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    /**
     * Get the property that was reviewed.
     */
    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function moderator()
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }

    public function moderationLogs()
    {
        return $this->hasMany(ReviewModerationLog::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }
}
