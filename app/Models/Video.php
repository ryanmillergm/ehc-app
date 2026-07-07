<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Video extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'source_type',
        'embed_url',
        'disk',
        'path',
        'public_url',
        'mime_type',
        'extension',
        'size_bytes',
        'duration_seconds',
        'poster_image_id',
        'title',
        'alt_text',
        'description',
        'is_decorative',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_decorative' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function posterImage(): BelongsTo
    {
        return $this->belongsTo(Image::class, 'poster_image_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function videoables(): HasMany
    {
        return $this->hasMany(Videoable::class);
    }

    public function resolvedUrl(): string
    {
        if ($this->source_type === 'embed') {
            return (string) $this->embed_url;
        }

        if (filled($this->public_url)) {
            return (string) $this->public_url;
        }

        return url(Storage::disk((string) $this->disk)->url((string) $this->path));
    }
}
