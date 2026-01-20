<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VolunteerNeed extends Model
{
    /** @use HasFactory<\Database\Factories\VolunteerNeedFactory> */
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'description',
        'is_active',
        'event_id',
        'capacity',
        'application_form_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function applications(): HasMany
    {
        return $this->hasMany(VolunteerApplication::class);
    }

    public function applicationForm(): BelongsTo
    {
        return $this->belongsTo(ApplicationForm::class, 'application_form_id');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
