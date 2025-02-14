<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Language extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'title',
        'iso_code',
        'locale',
        'right_to_left',
        'created_at',
    ];

    /**
     * Get the translations for a language.
     */
    public function pageTranslations(): HasMany
    {
        return $this->hasMany(PageTranslation::class);
    }
}
