<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
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

    /**
     * Global marketing opt-in:
     * - subscribed_at exists
     * - unsubscribed_at is null
     */
    public function scopeMarketingOptedIn(Builder $q): Builder
    {
        return $q->whereNotNull('subscribed_at')
                 ->whereNull('unsubscribed_at');
    }

    /**
     * Subscriber is opted-in to a specific list (by key),
     * based on the pivot row unsubscribed_at.
     */
    public function scopeSubscribedToListKey(Builder $q, string $listKey): Builder
    {
        return $q->whereHas('lists', function ($lists) use ($listKey) {
            $lists->where('email_lists.key', $listKey)
                  ->whereNull('email_list_subscriber.unsubscribed_at');
        });
    }

    public function scopeSubscribedToListId(Builder $q, int $listId): Builder
    {
        return $q->whereHas('lists', function ($lists) use ($listId) {
            $lists->where('email_lists.id', $listId)
                  ->whereNull('email_list_subscriber.unsubscribed_at');
        });
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

    /**
     * Convenience: "can I send this marketing list email to this subscriber?"
     */
    public function canReceiveMarketingList(string $listKey): bool
    {
        if ($this->isUnsubscribed() || is_null($this->subscribed_at)) {
            return false;
        }

        $list = $this->lists()->where('email_lists.key', $listKey)->first();

        // If they have no pivot row, treat as not subscribed (you can change this rule if you want)
        if (! $list) {
            return false;
        }

        return is_null($list->pivot->unsubscribed_at);
    }
}
