<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class EmailList extends Model
{
    use HasFactory;
    
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

    public function campaigns(): HasMany
    {
        return $this->hasMany(EmailCampaign::class, 'email_list_id');
    }

    protected static function booted(): void
    {
        static::updating(function (EmailList $list) {
            if ($list->isDirty('purpose') && $list->campaigns()->exists()) {
                throw ValidationException::withMessages([
                    'purpose' => 'Cannot change purpose of a list that already has email campaigns.',
                ]);
            }
        });
    }
}
