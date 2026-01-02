<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Validation\ValidationException;

class EmailCampaign extends Model
{
    use HasFactory;
    
    public const STATUS_DRAFT   = 'draft';
    public const STATUS_SENDING = 'sending';
    public const STATUS_SENT    = 'sent';
    public const STATUS_FAILED  = 'failed';

    protected $fillable = [
        'email_list_id',
        'subject',
        'body_html',
        'status',
        'queued_at',
        'sent_at',
        'sent_count',
        'pending_chunks',
        'created_by',
        'last_error',
    ];

    protected $casts = [
        'queued_at' => 'datetime',
        'sent_at'   => 'datetime',
    ];

    public function emailList(): BelongsTo
    {
        return $this->belongsTo(EmailList::class, 'email_list_id');
    }

    public function list(): BelongsTo
    {
        return $this->belongsTo(EmailList::class, 'email_list_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(EmailCampaignDelivery::class, 'email_campaign_id');
    }

    public function isSendable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_FAILED], true);
    }

    protected static function booted(): void
    {
        static::saving(function (EmailCampaign $campaign) {
            // avoid queries if not set yet
            if (! filled($campaign->email_list_id)) {
                return;
            }

            $purpose = EmailList::query()
                ->whereKey($campaign->email_list_id)
                ->value('purpose');

            if ($purpose !== 'marketing') {
                throw ValidationException::withMessages([
                    'email_list_id' => 'Email campaigns must be attached to a marketing list.',
                ]);
            }
        });
    }
}
