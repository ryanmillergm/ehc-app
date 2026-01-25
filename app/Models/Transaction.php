<?php

namespace App\Models;

use App\Models\Concerns\HasStageMetadata;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transaction extends Model
{
    use HasFactory;
    use HasStageMetadata;

    protected $fillable = [
        'user_id',
        'pledge_id',
        'payment_intent_id',
        'subscription_id',
        'setup_intent_id',
        'stripe_invoice_id',
        'charge_id',
        'customer_id',
        'payment_method_id',
        'attempt_id',
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
        'paid_at'   => 'datetime',
        'metadata'  => 'array',
        'amount_cents' => 'int',
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
