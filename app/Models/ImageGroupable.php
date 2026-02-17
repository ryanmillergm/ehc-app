<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ImageGroupable extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'image_group_id',
        'image_groupable_type',
        'image_groupable_id',
        'role',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(ImageGroup::class, 'image_group_id');
    }

    public function imageGroupable(): MorphTo
    {
        return $this->morphTo();
    }
}
