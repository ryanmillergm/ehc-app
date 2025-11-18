<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pledge extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'user_id',
        'stripe_subscription_id',
        'stripe_customer_id',
        'stripe_price_id',
        'amount_cents',
        'currency',
        'interval',
        'status',
        'cancel_at_period_end',
        'current_period_start',
        'current_period_end',
        'last_pledge_at',
        'next_pledge_at',
        'latest_invoice_id',
        'latest_payment_intent_id',
        'donor_email',
        'donor_name',
        'metadata',
    ];

    protected $casts = [
        'cancel_at_period_end' => 'bool',
        'current_period_start' => 'datetime',
        'current_period_end'   => 'datetime',
        'last_pledge_at'       => 'datetime',
        'next_pledge_at'       => 'datetime',
        'metadata'             => 'array',
    ];

    public function getAmountDollarsAttribute(): float
    {
        return $this->amount_cents / 100;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
