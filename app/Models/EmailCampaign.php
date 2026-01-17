<?php

namespace App\Models;

use App\Support\HtmlFragments;
use Illuminate\Database\Eloquent\Casts\Attribute;
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

        'editor',
        'design_json',
        'design_html',
        'design_css',

        'body_html',
        'body_text',

        'status',
        'queued_at',
        'sent_at',
        'sent_count',
        'pending_chunks',
        'created_by',
        'last_error',
    ];

    protected $casts = [
        'design_json' => 'array',
        'queued_at' => 'datetime',
        'sent_at'   => 'datetime',
    ];

    /**
     * Always store ONLY the body inner HTML in design_html.
     * This prevents GrapesJS parsing/perf issues caused by saving <body>...</body>
     * (or full documents) into the design fragment field.
     */
    protected function designHtml(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => HtmlFragments::bodyInner($value),
        );
    }

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
        static::creating(fn (EmailCampaign $campaign) => $campaign->assertMarketingList());

        static::updating(function (EmailCampaign $campaign) {
            if ($campaign->isDirty('email_list_id')) {
                $campaign->assertMarketingList();
            }
        });
    }

    protected function assertMarketingList(): void
    {
        if (! filled($this->email_list_id)) {
            return;
        }

        $purpose = EmailList::query()
            ->whereKey($this->email_list_id)
            ->value('purpose');

        if ($purpose !== 'marketing') {
            throw ValidationException::withMessages([
                'email_list_id' => 'Email campaigns must be attached to a marketing list.',
            ]);
        }
    }
}
