<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'pledge_id',
        'payment_intent_id',
        'subscription_id',
        'charge_id',
        'customer_id',
        'payment_method_id',
        'amount_cents',
        'currency',
        'type',
        'status',
        'payer_email',
        'payer_name',
        'receipt_url',
        'source',
        'metadata',
        'paid_at',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pledge(): BelongsTo
    {
        return $this->belongsTo(Pledge::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function getAmountDollarsAttribute(): float
    {
        return $this->amount_cents / 100;
    }
}
