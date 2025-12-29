<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class EmailList extends Model
{
    protected $fillable = [
        'key',
        'label',
        'description',
        'purpose',
        'is_default',
        'is_opt_outable',
    ];

    protected $casts = [
        'is_default' => 'bool',
        'is_opt_outable' => 'bool',
    ];

    public function subscribers(): BelongsToMany
    {
        return $this->belongsToMany(EmailSubscriber::class, 'email_list_subscriber')
            ->withPivot(['subscribed_at', 'unsubscribed_at'])
            ->withTimestamps();
    }
}
