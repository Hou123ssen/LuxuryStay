<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Image extends Model
{
    protected $fillable = ['property_id', 'path'];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }
}
