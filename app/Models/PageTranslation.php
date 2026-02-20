<?php

namespace App\Models;

use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class PageTranslation extends Model
{
    use HasFactory;

    /** @var array<string, mixed> */
    protected array $pendingSeoMeta = [];

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
        'template',
        'theme',
        'hero_mode',
        'hero_title',
        'hero_subtitle',
        'hero_cta_text',
        'hero_cta_url',
        'layout_data',
        'seo_title',
        'seo_description',
        'seo_og_image',
        'is_active',
        'published_at',
    ];

    protected $casts = [
        'layout_data' => 'array',
        'is_active' => 'boolean',
        'published_at' => 'datetime',
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
            Select::make('template')
                ->options([
                    'standard' => 'Standard',
                    'campaign' => 'Campaign',
                    'story' => 'Story',
                ])
                ->default('standard')
                ->required(),
            Select::make('theme')
                ->options([
                    'default' => 'Default',
                    'warm' => 'Warm',
                    'slate' => 'Slate',
                ])
                ->default('default')
                ->required(),
            Select::make('hero_mode')
                ->options([
                    'none' => 'None',
                    'image' => 'Image',
                    'video' => 'Video',
                    'slider' => 'Slider',
                ])
                ->default('none')
                ->required(),
            TextInput::make('hero_title')
                ->maxLength(255),
            Textarea::make('hero_subtitle')
                ->columnSpanFull(),
            TextInput::make('hero_cta_text')
                ->maxLength(255),
            TextInput::make('hero_cta_url')
                ->maxLength(500),
            TextInput::make('seo_title')
                ->maxLength(255),
            Textarea::make('seo_description')
                ->columnSpanFull(),
            TextInput::make('seo_og_image')
                ->maxLength(500),
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

    public function imageables(): MorphMany
    {
        return $this->morphMany(Imageable::class, 'imageable');
    }

    public function imageGroupables(): MorphMany
    {
        return $this->morphMany(ImageGroupable::class, 'image_groupable');
    }

    public function videoables(): MorphMany
    {
        return $this->morphMany(Videoable::class, 'videoable');
    }

    public function seoMeta(): MorphMany
    {
        return $this->morphMany(SeoMeta::class, 'seoable');
    }

    protected static function booted(): void
    {
        static::saved(function (self $model): void {
            $model->syncPendingSeoMeta();
        });
    }

    public function getSeoTitleAttribute(): ?string
    {
        return $this->resolvedSeoMeta()?->seo_title;
    }

    public function setSeoTitleAttribute(?string $value): void
    {
        $this->pendingSeoMeta['seo_title'] = $value;
        unset($this->attributes['seo_title']);
    }

    public function getSeoDescriptionAttribute(): ?string
    {
        return $this->resolvedSeoMeta()?->seo_description;
    }

    public function setSeoDescriptionAttribute(?string $value): void
    {
        $this->pendingSeoMeta['seo_description'] = $value;
        unset($this->attributes['seo_description']);
    }

    public function getSeoOgImageAttribute(): ?string
    {
        return $this->resolvedSeoMeta()?->seo_og_image;
    }

    public function setSeoOgImageAttribute(?string $value): void
    {
        $this->pendingSeoMeta['seo_og_image'] = $value;
        unset($this->attributes['seo_og_image']);
    }

    protected function syncPendingSeoMeta(): void
    {
        if ($this->pendingSeoMeta === []) {
            return;
        }

        $payload = [
            'seo_title' => Arr::get($this->pendingSeoMeta, 'seo_title'),
            'seo_description' => Arr::get($this->pendingSeoMeta, 'seo_description'),
            'seo_og_image' => Arr::get($this->pendingSeoMeta, 'seo_og_image'),
        ];

        $this->pendingSeoMeta = [];

        if (blank($payload['seo_title']) && blank($payload['seo_description']) && blank($payload['seo_og_image'])) {
            $this->seoMeta()
                ->where('target_key', '')
                ->where('language_id', $this->language_id)
                ->delete();
            return;
        }

        $this->seoMeta()->updateOrCreate(
            [
                'target_key' => '',
                'language_id' => $this->language_id,
            ],
            [
                'seo_title' => $payload['seo_title'],
                'seo_description' => $payload['seo_description'],
                'seo_og_image' => $payload['seo_og_image'],
                'is_active' => true,
            ]
        );
    }

    protected function resolvedSeoMeta(): ?SeoMeta
    {
        if (! $this->exists || ! $this->language_id) {
            return null;
        }

        if ($this->relationLoaded('seoMeta')) {
            return $this->seoMeta
                ->where('target_key', '')
                ->where('language_id', $this->language_id)
                ->first();
        }

        return $this->seoMeta()
            ->where('target_key', '')
            ->where('language_id', $this->language_id)
            ->where('is_active', true)
            ->first();
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
