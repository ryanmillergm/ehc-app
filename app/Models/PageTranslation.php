<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageTranslation extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id',
        'page_id',
        'language_id',
        'title',
        'slug',
        'description',
        'content',
        'is_active'
    ];

    /**
     * Get the Page that owns the PageTranslation.
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /**
     * Get the Language that owns the PageTranslation.
     */
    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }
}
