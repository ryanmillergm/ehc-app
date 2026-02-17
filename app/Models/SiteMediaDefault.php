<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteMediaDefault extends Model
{
    use HasFactory;

    protected $fillable = [
        'role',
        'image_id',
    ];

    public function image(): BelongsTo
    {
        return $this->belongsTo(Image::class);
    }
}
