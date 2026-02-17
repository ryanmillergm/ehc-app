<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class HomePageContent extends Model
{
    use HasFactory;

    protected $fillable = [
        'language_id',
        'seo_title',
        'seo_description',
        'hero_intro',
        'meeting_schedule',
        'meeting_location',
        'hero_image_id',
        'featured_image_id',
        'og_image_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    public function heroImage(): BelongsTo
    {
        return $this->belongsTo(Image::class, 'hero_image_id');
    }

    public function featuredImage(): BelongsTo
    {
        return $this->belongsTo(Image::class, 'featured_image_id');
    }

    public function ogImage(): BelongsTo
    {
        return $this->belongsTo(Image::class, 'og_image_id');
    }

    public function imageables(): MorphMany
    {
        return $this->morphMany(Imageable::class, 'imageable');
    }

    public function imageGroupables(): MorphMany
    {
        return $this->morphMany(ImageGroupable::class, 'image_groupable');
    }
}
