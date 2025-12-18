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
        'setup_intent_id',
        'attempt_id',
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


    // --------------------------
    // Accessors
    // --------------------------

    public function getAmountDollarsAttribute(): float
    {
        return $this->amount_cents / 100;
    }

    /**
     * Status text for a small badge.
     * Examples:
     *  - "Active"
     *  - "Active (will cancel at period end)"
     *  - "Canceled"
     *  - "Past due"
     */
    public function getStatusBadgeLabelAttribute(): string
    {
        if ($this->status === 'active' && $this->cancel_at_period_end) {
            return 'Active (will cancel at period end)';
        }

        return ucfirst($this->status ?? 'unknown');
    }

    /**
     * Human label like:
     *  - "Renews on Nov 18, 2025"
     *  - "Cancels on Nov 18, 2025"
     * Returns null if we don’t have a period end.
     */
    public function getRenewsOrCancelsLabelAttribute(): ?string
    {
        if (! $this->current_period_end) {
            return null;
        }

        $date = $this->current_period_end->format('M j, Y');

        if ($this->status === 'active' && $this->cancel_at_period_end) {
            return "Cancels on {$date}";
        }

        if ($this->status === 'active') {
            return "Renews on {$date}";
        }

        if ($this->status === 'canceled') {
            return "Canceled on {$date}";
        }

        return null;
    }

    // --------------------------
    // Relationships
    // --------------------------

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
