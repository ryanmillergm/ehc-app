<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function applications(): HasMany
    {
        return $this->hasMany(VolunteerApplication::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
