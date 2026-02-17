<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Image extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'disk',
        'path',
        'public_url',
        'mime_type',
        'extension',
        'size_bytes',
        'width',
        'height',
        'image_type_id',
        'title',
        'alt_text',
        'description',
        'caption',
        'credit',
        'is_decorative',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_decorative' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function type(): BelongsTo
    {
        return $this->belongsTo(ImageType::class, 'image_type_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function groupItems(): HasMany
    {
        return $this->hasMany(ImageGroupItem::class);
    }

    public function imageables(): HasMany
    {
        return $this->hasMany(Imageable::class);
    }

    public function groupables(): HasMany
    {
        return $this->hasMany(ImageGroupable::class);
    }

    public function siteDefaults(): HasMany
    {
        return $this->hasMany(SiteMediaDefault::class);
    }

    public function resolvedUrl(): string
    {
        if (filled($this->public_url)) {
            return (string) $this->public_url;
        }

        return url(Storage::disk($this->disk)->url($this->path));
    }
}
