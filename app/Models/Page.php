<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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
    
    /**
     * Scope All Active Pages With Active PageTranslations By Language
     *
     * @param  mixed $query
     * @return void
     */
    public function scopeAllActivePagesWithTranslationsByLanguage(Builder $query)
    {
        $query->withWhereHas('pageTranslations', function ($query) {
            $query->where('is_active', true)->where( 'language_id', session('language_id'));
        })->where('is_active', true);
    }

    /**
     * Scope Get Translation by Language
     *
     * @param  mixed $query
     * @return void
     */
    public function scopeTranslationByDefaultLanguage(Builder $query)
    {
        $language = Language::first();

        $query->where(function ($query, $language) {
            $query->where('language_id', $language)->where('is_active', true);
        });
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
