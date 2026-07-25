<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable,  HasApiTokens;

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    protected $fillabel = [
        'name',
        'email',
        'password',
    ];

    /**
     * Get the properties owned by the user.
     */
    public function properties()
    {
        return $this->hasMany(Property::class);
    }

    /**
     * Get the bookings made by the user.
     */
    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Get the reviews written by the user.
     */
    public function reviews()

    {

        return $this->hasMany(Review::class);
    }

    /**
     * Get the messages sent by the user.
     */
    public function sentMessages()
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    /**
     * Get the messages received by the user.
     */
    public function receivedMessages()
    {
        return $this->hasMany(Message::class, 'receiver_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
