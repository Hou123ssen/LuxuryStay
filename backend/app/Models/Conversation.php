<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    protected $fillable = ['property_id', 'user_one_id', 'user_two_id'];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function userOne()
    {
        return $this->belongsTo(User::class, 'user_one_id');
    }

    public function userTwo()
    {
        return $this->belongsTo(User::class, 'user_two_id');
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function reads()
    {
        return $this->hasMany(ConversationRead::class);
    }

    public function callSessions()
    {
        return $this->hasMany(CallSession::class);
    }

    public function lastMessage()
    {
        return $this->hasOne(Message::class)->latest();
    }
}
