<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    /**
     * Get the user that wrote the review.
     */

    protected $fillable = [
    'user_id',
    'property_id',
    'booking_id',
    'rating',
    'comment'
];
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
}
