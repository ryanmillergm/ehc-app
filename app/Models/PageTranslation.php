<?php

namespace App\Models;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

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

    public static function getForm($pageId = null): array
    {
        return [
            Select::make('page_id')
                ->hidden( function () use ($pageId) {
                    return $pageId != null;
                })
                ->label('Page')
                ->relationship('page', 'title')
                ->required(),
            Select::make('language_id')
                ->label('Language')
                ->relationship('language', 'title')
                ->required(),
            TextInput::make('title')
                ->live(onBlur: true)
                ->afterStateUpdated(function (Get $get, Set $set, ?string $old, ?string $state) {
                    if (($get('slug') ?? '') !== Str::slug($old)) {
                        return;
                    }

                    $set('slug', Str::slug($state));
                })
                ->required()
                ->maxLength(255),
            TextInput::make('slug')
                ->required()
                ->maxLength(255),
            Textarea::make('description')
                ->required()
                ->columnSpanFull(),
            Textarea::make('content')
                ->required()
                ->columnSpanFull(),
            Toggle::make('is_active')
                ->required(),
        ];
    }

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


    /**
     * Scope Get Translation with Page by Slug
     *
     * @param  mixed $query
     * @return void
     */
    public function scopeTranslationBySlug(Builder $query, $slug)
    {
        $query->with('page')->where('slug', $slug);
    }


    /**
     * Scope Get Active Translation and Page
     *
     * @param  mixed $query
     * @return void
     */
    public function scopeActiveTranslation(Builder $query)
    {
        $query->where('is_active', true);
    }


    /**
     * Scope Get Translation by Language
     *
     * @param  mixed $query
     * @return void
     */
    public function scopeByLanguage(Builder $query, $language)
    {
        $query->where( 'language_id', $language);
    }
}
