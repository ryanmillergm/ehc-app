<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Page extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'title',
        'is_active',
    ];

    /**
     * Get the translations for a page.
     */
    public function translations(): HasMany
    {
        return $this->hasMany(PageTranslation::class);
    }

    /**
     * Get the Page that owns the PageTranslation.
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function addTranslation($request)
    {
        return $this->translations()->create($request);
    }

}
