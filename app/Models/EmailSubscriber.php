<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class EmailSubscriber extends Model
{
    protected $fillable = [
        'email',
        'first_name',
        'last_name',
        'user_id',
        'unsubscribe_token',
        'subscribed_at',
        'unsubscribed_at',
    ];

    protected $casts = [
        'subscribed_at' => 'datetime',
        'unsubscribed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lists(): BelongsToMany
    {
        return $this->belongsToMany(EmailList::class, 'email_list_subscriber')
            ->withPivot(['subscribed_at', 'unsubscribed_at'])
            ->withTimestamps();
    }

    public function getNameAttribute(): ?string
    {
        $first = trim((string) $this->first_name);
        $last  = trim((string) $this->last_name);
        $full  = trim($first.' '.$last);

        return $full !== '' ? $full : $this->user?->name;
    }

    public function isUnsubscribed(): bool
    {
        return ! is_null($this->unsubscribed_at);
    }

    public function isSubscribed(): bool
    {
        return is_null($this->unsubscribed_at) && ! is_null($this->subscribed_at);
    }
}
