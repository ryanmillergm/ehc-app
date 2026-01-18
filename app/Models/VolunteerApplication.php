<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VolunteerApplication extends Model
{
    /** @use HasFactory<\Database\Factories\VolunteerApplicationFactory> */
    use HasFactory;

    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_REVIEWING = 'reviewing';
    public const STATUS_ACCEPTED  = 'accepted';
    public const STATUS_REJECTED  = 'rejected';
    public const STATUS_WITHDRAWN = 'withdrawn';

    protected $fillable = [
        'user_id',
        'volunteer_need_id',
        'status',
        'message',
        'interests',
        'availability',
        'reviewed_by',
        'reviewed_at',
        'internal_notes',
    ];

    protected $casts = [
        'interests'     => 'array',
        'availability'  => 'array',
        'reviewed_at'   => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function need(): BelongsTo
    {
        return $this->belongsTo(VolunteerNeed::class, 'volunteer_need_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
