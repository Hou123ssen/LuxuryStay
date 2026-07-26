<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Image extends Model
{
    protected $fillable = ['property_id', 'path'];

    protected $appends = ['url'];

    public function getUrlAttribute(): ?string
    {
        if (! $this->path) {
            return null;
        }

        $storageUrl = parse_url(Storage::disk('public')->url($this->path), PHP_URL_PATH)
            ?: Storage::disk('public')->url($this->path);
        $baseUrl = request()?->getSchemeAndHttpHost() ?: rtrim(config('app.url'), '/');

        return rtrim($baseUrl, '/').'/'.ltrim($storageUrl, '/');
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }
}
