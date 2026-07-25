<?php

namespace App\Models;

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
}
