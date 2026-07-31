<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_REVIEWED = 'reviewed';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_REJECTED = 'rejected';

    public const SEVERITY_LOW = 'low';
    public const SEVERITY_NORMAL = 'normal';
    public const SEVERITY_HIGH = 'high';
    public const SEVERITY_CRITICAL = 'critical';

    public const CATEGORY_INACCURATE_LISTING = 'inaccurate_listing';
    public const CATEGORY_UNSAFE_PROPERTY = 'unsafe_property';
    public const CATEGORY_HOST_ISSUE = 'host_issue';
    public const CATEGORY_PAYMENT_ISSUE = 'payment_issue';
    public const CATEGORY_CANCELLATION_ISSUE = 'cancellation_issue';
    public const CATEGORY_SCAM_OR_FRAUD = 'scam_or_fraud';
    public const CATEGORY_OTHER = 'other';

    public const CATEGORIES = [
        self::CATEGORY_INACCURATE_LISTING,
        self::CATEGORY_UNSAFE_PROPERTY,
        self::CATEGORY_HOST_ISSUE,
        self::CATEGORY_PAYMENT_ISSUE,
        self::CATEGORY_CANCELLATION_ISSUE,
        self::CATEGORY_SCAM_OR_FRAUD,
        self::CATEGORY_OTHER,
    ];

    public const OPEN_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_REVIEWED,
    ];

    protected $fillable = [
        'reporter_user_id',
        'property_id',
        'booking_id',
        'reported_user_id',
        'category',
        'description',
        'status',
        'severity',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reporter_user_id');
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function reportedUser()
    {
        return $this->belongsTo(User::class, 'reported_user_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', self::OPEN_STATUSES);
    }
}
