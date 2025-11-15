<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'name',
        'slug',
    ];

    /**
     * Get the user that owns the team.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The users that belong to the team.
     */
    public function users()
    {
        return $this->belongsToMany(User::class);
    }

    /**
     * Filament Multi-Tenancy Relationship to Children
     *
     * @return HasMany
     */
    public function children(): HasMany
    {
        return $this->hasMany(Child::class);
    }

    /**
     * Filament Multi-Tenancy Relationship
     *
     * @return BelongsTo
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}
