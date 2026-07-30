<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{

   protected $fillable = [
    'user_id',
    'property_id',
    'start_date',
    'end_date',
    'total_price',
    'status'
];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
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

    public function review()
    {
        return $this->hasOne(Review::class);
    }
}
