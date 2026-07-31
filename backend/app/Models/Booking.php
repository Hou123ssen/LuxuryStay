<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_COMPLETED = 'completed';

    public const CANCELLATION_ACTOR_GUEST = 'guest';
    public const CANCELLATION_ACTOR_OWNER = 'owner';

   protected $fillable = [
    'user_id',
    'property_id',
    'start_date',
    'end_date',
    'total_price',
    'status',
    'cancelled_at',
    'cancelled_by_user_id',
    'cancellation_actor',
    'cancellation_reason',
];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function setStartDateAttribute($value): void
    {
        $this->attributes['start_date'] = Carbon::parse($value)->toDateString();
    }

    public function setEndDateAttribute($value): void
    {
        $this->attributes['end_date'] = Carbon::parse($value)->toDateString();
    }
    /**
     * Get the user that made the booking.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    /**
     * Get the property that was booked.
     */
    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function cancelledBy()
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    public function review()
    {
        return $this->hasOne(Review::class);
    }

    public function hasStayStarted(): bool
    {
        return Carbon::today()->greaterThanOrEqualTo(Carbon::parse($this->start_date)->startOfDay());
    }

    public function canBeCancelledByGuest(User $user): bool
    {
        return (int) $this->user_id === (int) $user->id
            && in_array($this->status, [self::STATUS_PENDING, self::STATUS_ACCEPTED], true)
            && ! $this->hasStayStarted();
    }

    public function canBeCancelledByOwner(User $user): bool
    {
        $ownerId = $this->relationLoaded('property')
            ? $this->property?->user_id
            : $this->property()->value('user_id');

        return (int) $ownerId === (int) $user->id
            && $this->status === self::STATUS_ACCEPTED
            && ! $this->hasStayStarted();
    }

    public function cancelBy(User $user, string $actor, ?string $reason = null): void
    {
        $this->forceFill([
            'status' => self::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancelled_by_user_id' => $user->id,
            'cancellation_actor' => $actor,
            'cancellation_reason' => $reason,
        ])->save();
    }
}
