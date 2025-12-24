<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailSubscriber extends Model
{
    protected $fillable = [
        'email',
        'name',
        'user_id',
        'preferences',
        'unsubscribe_token',
        'subscribed_at',
        'unsubscribed_at',
    ];

    protected $casts = [
        'preferences' => 'array',
        'subscribed_at' => 'datetime',
        'unsubscribed_at' => 'datetime',
    ];

    public function isUnsubscribed(): bool
    {
        return ! is_null($this->unsubscribed_at);
    }

    public function isSubscribed(): bool
    {
        return is_null($this->unsubscribed_at) && ! is_null($this->subscribed_at);
    }
}
