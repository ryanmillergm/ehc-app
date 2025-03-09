<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function scopePageTranslationsByLanguage($query)
    {
        $pages = $query->withWhereHas('pageTranslations', function ($query) {
            $query->where('is_active', true)->where( 'language_id', session('language_id'));
        })->get();

        return $pages;
    }

    /**
     * Get the translations for a page.
     */
    public function pageTranslations(): HasMany
    {
        return $this->hasMany(PageTranslation::class);
    }


    public function addTranslation($request)
    {
        return $this->pageTranslations()->create($request);
    }

}
