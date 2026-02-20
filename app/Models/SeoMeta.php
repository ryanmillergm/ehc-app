<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SeoMeta extends Model
{
    use HasFactory;

    protected $table = 'seo_meta';

    protected $fillable = [
        'seoable_type',
        'seoable_id',
        'target_key',
        'language_id',
        'seo_title',
        'seo_description',
        'seo_og_image',
        'canonical_path',
        'robots',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function seoable(): MorphTo
    {
        return $this->morphTo();
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeForTarget(Builder $query, string $type, int $id, string $targetKey = ''): void
    {
        $query->where('seoable_type', $type)
            ->where('seoable_id', $id)
            ->where('target_key', $targetKey);
    }
}
