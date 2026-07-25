<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Image;

class Property extends Model



{


    protected $fillable = [
        'user_id',
        'title',
        'description',
        'type',
        'price_per_night',
        'city',
        'address',
    ];
    /**
     * Get the user that owns the property.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the images for the property.
     */
    public function images()
    {
        return $this->hasMany(Image::class);
    }

    /**
     * Get the bookings for the property.
     */
    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Get the reviews for the property.
     */
    public function reviews()
    {
        return $this->hasMany(Review::class);
    }
    public function favorites()
    {
        return $this->hasMany(\App\Models\Favorite::class);
    }
}
