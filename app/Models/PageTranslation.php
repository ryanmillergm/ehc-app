<?php

namespace App\Models;

use App\Enums\HeroHeight;
use App\Enums\HeroOverlay;
use App\Enums\HeroStyle;
use App\Enums\HeroTextAlign;
use App\Enums\HeroTextWidth;
use App\Enums\PageRenderMode;
use App\Enums\PageTemplate;
use App\Models\Concerns\SanitizesCmsHtml;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\CodeEditor\Enums\Language as CodeLanguage;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PageTranslation extends Model
{
    use HasFactory;
    use SanitizesCmsHtml;

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
        'render_mode',
        'template',
        'theme',
        'hero_mode',
        'hero_style',
        'hero_height',
        'hero_overlay',
        'hero_text_align',
        'hero_text_width',
        'hero_title',
        'hero_subtitle',
        'hero_cta_text',
        'hero_cta_url',
        'content_blocks',
        'custom_html',
        'custom_html_is_trusted',
        'seo_title',
        'seo_description',
        'seo_og_image',
        'is_active',
        'published_at',
    ];

    protected $casts = [
        'content_blocks' => 'array',
        'custom_html_is_trusted' => 'boolean',
        'is_active' => 'boolean',
        'published_at' => 'datetime',
    ];

    public static function getForm($pageId = null): array
    {
        $renderMode = fn (Get $get): string => (string) ($get('render_mode') ?: PageRenderMode::Template->value);

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
            Textarea::make('title')
                ->rows(2)
                ->live(onBlur: true)
                ->afterStateUpdated(function (Get $get, Set $set, ?string $old, ?string $state) {
                    if (($get('slug') ?? '') !== Str::slug($old)) {
                        return;
                    }

                    $set('slug', Str::slug($state));
                })
                ->required()
                ->helperText('Plain text or HTML accepted. Sanitized on save.'),
            TextInput::make('slug')
                ->required()
                ->maxLength(255),
            Textarea::make('description')
                ->required()
                ->helperText('Plain text or HTML accepted. Sanitized on save.')
                ->columnSpanFull(),
            Textarea::make('content')
                ->required()
                ->helperText('Plain text or HTML accepted. Sanitized on save.')
                ->columnSpanFull(),
            Select::make('render_mode')
                ->label('Render Mode')
                ->options(PageRenderMode::options())
                ->default(PageRenderMode::Template->value)
                ->live()
                ->required(),
            Select::make('template')
                ->options(PageTemplate::options())
                ->default(PageTemplate::Standard->value)
                ->visible(fn (Get $get): bool => $renderMode($get) === PageRenderMode::Template->value)
                ->required(),
            Select::make('theme')
                ->options([
                    'default' => 'Default',
                    'warm' => 'Warm',
                    'slate' => 'Slate',
                ])
                ->default('default')
                ->visible(fn (Get $get): bool => $renderMode($get) === PageRenderMode::Template->value)
                ->required(),
            Select::make('hero_mode')
                ->options([
                    'none' => 'None',
                    'image' => 'Image',
                    'video' => 'Video',
                    'slider' => 'Slider',
                ])
                ->default('none')
                ->visible(fn (Get $get): bool => $renderMode($get) === PageRenderMode::Template->value)
                ->required(),
            Select::make('hero_style')
                ->label('Hero Style')
                ->options(HeroStyle::options())
                ->default(HeroStyle::Contained->value)
                ->visible(fn (Get $get): bool => $renderMode($get) === PageRenderMode::Template->value)
                ->required(),
            Select::make('hero_height')
                ->label('Hero Height')
                ->options(HeroHeight::options())
                ->default(HeroHeight::H80->value)
                ->visible(fn (Get $get): bool => $renderMode($get) === PageRenderMode::Template->value)
                ->required(),
            Select::make('hero_overlay')
                ->label('Hero Overlay')
                ->options(HeroOverlay::options())
                ->default(HeroOverlay::Medium->value)
                ->visible(fn (Get $get): bool => $renderMode($get) === PageRenderMode::Template->value)
                ->required(),
            Select::make('hero_text_align')
                ->label('Hero Text Align')
                ->options(HeroTextAlign::options())
                ->default(HeroTextAlign::Left->value)
                ->visible(fn (Get $get): bool => $renderMode($get) === PageRenderMode::Template->value)
                ->required(),
            Select::make('hero_text_width')
                ->label('Hero Text Width')
                ->options(HeroTextWidth::options())
                ->default(HeroTextWidth::Normal->value)
                ->visible(fn (Get $get): bool => $renderMode($get) === PageRenderMode::Template->value)
                ->required(),
            Textarea::make('hero_title')
                ->rows(2)
                ->visible(fn (Get $get): bool => $renderMode($get) === PageRenderMode::Template->value)
                ->helperText('Plain text or HTML accepted. Sanitized on save.'),
            Textarea::make('hero_subtitle')
                ->helperText('Plain text or HTML accepted. Sanitized on save.')
                ->visible(fn (Get $get): bool => $renderMode($get) === PageRenderMode::Template->value)
                ->columnSpanFull(),
            Textarea::make('hero_cta_text')
                ->rows(2)
                ->visible(fn (Get $get): bool => $renderMode($get) === PageRenderMode::Template->value)
                ->helperText('Plain text or HTML accepted. Sanitized on save.'),
            TextInput::make('hero_cta_url')
                ->visible(fn (Get $get): bool => $renderMode($get) === PageRenderMode::Template->value)
                ->maxLength(500),
            Builder::make('content_blocks')
                ->label('Content Blocks')
                ->visible(fn (Get $get): bool => $renderMode($get) === PageRenderMode::Blocks->value)
                ->collapsible()
                ->collapsed()
                ->cloneable()
                ->reorderableWithButtons()
                ->blockNumbers(false)
                ->blocks(self::contentBlocksSchema())
                ->columnSpanFull(),
            CodeEditor::make('custom_html')
                ->label('Custom HTML')
                ->helperText('Rendered inside the app layout. Scripts are never allowed.')
                ->language(CodeLanguage::Html)
                ->visible(fn (Get $get): bool => $renderMode($get) === PageRenderMode::Custom->value)
                ->columnSpanFull(),
            Toggle::make('custom_html_is_trusted')
                ->label('Trusted HTML Mode')
                ->helperText('Allows a broader HTML set. Scripts and event handlers remain blocked.')
                ->visible(function (Get $get): bool {
                    if ((string) ($get('render_mode') ?: PageRenderMode::Template->value) !== PageRenderMode::Custom->value) {
                        return false;
                    }

                    return (bool) auth()->user()?->can('pages.render_unsafe_html');
                }),
            Toggle::make('is_active')
                ->required(),
        ];
    }

    /**
     * @return array<int, Block>
     */
    protected static function contentBlocksSchema(): array
    {
        return [
            Block::make('hero')->schema([
                TextInput::make('eyebrow'),
                Textarea::make('heading')->rows(2)->required(),
                Textarea::make('subheading'),
                Select::make('hero_mode')
                    ->label('Hero Media')
                    ->options([
                        'none' => 'None',
                        'image' => 'Header Image',
                        'video' => 'Hero Video',
                        'slider' => 'Hero Slider',
                    ])
                    ->helperText('Uses this page translation\'s existing media relationships.')
                    ->default('none'),
                Select::make('hero_style')
                    ->options(HeroStyle::options())
                    ->default(HeroStyle::Contained->value),
                Select::make('hero_height')
                    ->options(HeroHeight::options())
                    ->default(HeroHeight::H80->value),
                Select::make('hero_overlay')
                    ->options(HeroOverlay::options())
                    ->default(HeroOverlay::Medium->value),
                Select::make('hero_text_align')
                    ->options(HeroTextAlign::options())
                    ->default(HeroTextAlign::Left->value),
                Select::make('hero_text_width')
                    ->options(HeroTextWidth::options())
                    ->default(HeroTextWidth::Normal->value),
                TextInput::make('primary_cta_text'),
                TextInput::make('primary_cta_url'),
                TextInput::make('secondary_cta_text'),
                TextInput::make('secondary_cta_url'),
            ]),
            Block::make('rich_text')->schema([
                RichEditor::make('body')->required()->columnSpanFull(),
            ]),
            Block::make('image')->schema([
                TextInput::make('src')->required(),
                TextInput::make('alt'),
                TextInput::make('caption'),
            ]),
            Block::make('gallery')->schema([
                Repeater::make('items')
                    ->label('Images')
                    ->schema([
                        Select::make('source_type')
                            ->label('Image Source')
                            ->options([
                                'existing' => 'Existing Image',
                                'upload' => 'Upload Image',
                                'url' => 'Source URL',
                            ])
                            ->default('existing')
                            ->live()
                            ->required(),
                        Select::make('image_id')
                            ->label('Existing Image')
                            ->options(fn (): array => self::imageSelectOptions())
                            ->searchable()
                            ->preload()
                            ->visible(fn (Get $get): bool => $get('source_type') === 'existing')
                            ->required(fn (Get $get): bool => $get('source_type') === 'existing'),
                        FileUpload::make('upload_path')
                            ->label('Upload Image')
                            ->image()
                            ->directory('cms/images/' . now()->format('Y/m'))
                            ->disk('public')
                            ->visibility('public')
                            ->visible(fn (Get $get): bool => $get('source_type') === 'upload')
                            ->required(fn (Get $get): bool => $get('source_type') === 'upload'),
                        TextInput::make('src')
                            ->label('Source URL')
                            ->url()
                            ->maxLength(500)
                            ->visible(fn (Get $get): bool => $get('source_type') === 'url')
                            ->required(fn (Get $get): bool => $get('source_type') === 'url'),
                        TextInput::make('title')
                            ->maxLength(255),
                        TextInput::make('alt')
                            ->label('Alt Text')
                            ->maxLength(255),
                        TextInput::make('caption')
                            ->maxLength(255),
                    ])
                    ->reorderableWithButtons()
                    ->collapsible()
                    ->itemLabel(fn (?array $state): ?string => $state['title'] ?? $state['alt'] ?? $state['src'] ?? null)
                    ->columnSpanFull(),
            ]),
            Block::make('cta')->schema([
                Textarea::make('title')->rows(2)->required(),
                Textarea::make('body'),
                TextInput::make('button_text'),
                TextInput::make('button_url'),
            ]),
            Block::make('stats')->schema([
                Repeater::make('items')
                    ->schema([
                        TextInput::make('label')
                            ->required()
                            ->maxLength(120),
                        TextInput::make('value')
                            ->required()
                            ->maxLength(120),
                    ])
                    ->reorderableWithButtons()
                    ->collapsible()
                    ->itemLabel(fn (?array $state): ?string => $state['label'] ?? null)
                    ->columnSpanFull(),
            ]),
            Block::make('faq_teaser')->schema([
                Textarea::make('title')->rows(2),
                Textarea::make('body'),
                TextInput::make('button_text'),
                TextInput::make('button_url'),
            ]),
            Block::make('divider')->schema([
                Select::make('style')->options([
                    'line' => 'Line',
                    'space' => 'Spacing',
                ])->default('line'),
            ]),
            Block::make('quote')->schema([
                Textarea::make('quote')->rows(3)->required(),
                TextInput::make('attribution'),
            ]),
            Block::make('testimonials')->schema([
                Repeater::make('items')
                    ->schema([
                        Textarea::make('quote')
                            ->rows(3)
                            ->required(),
                        TextInput::make('name')
                            ->maxLength(255),
                    ])
                    ->reorderableWithButtons()
                    ->collapsible()
                    ->itemLabel(fn (?array $state): ?string => $state['name'] ?? null)
                    ->columnSpanFull(),
            ]),
            Block::make('timeline')->schema([
                Repeater::make('items')
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('body')
                            ->rows(3),
                    ])
                    ->reorderableWithButtons()
                    ->collapsible()
                    ->itemLabel(fn (?array $state): ?string => $state['title'] ?? null)
                    ->columnSpanFull(),
            ]),
            Block::make('pricing')->schema([
                Repeater::make('items')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('price')
                            ->maxLength(120),
                        Repeater::make('features')
                            ->schema([
                                TextInput::make('text')
                                    ->required()
                                    ->maxLength(255),
                            ])
                            ->reorderableWithButtons()
                            ->collapsible()
                            ->itemLabel(fn (?array $state): ?string => $state['text'] ?? null)
                            ->columnSpanFull(),
                    ])
                    ->reorderableWithButtons()
                    ->collapsible()
                    ->itemLabel(fn (?array $state): ?string => $state['name'] ?? null)
                    ->columnSpanFull(),
            ]),
            Block::make('embed')->schema([
                TextInput::make('url')->required(),
                Textarea::make('caption'),
            ]),
            Block::make('feature_grid')->schema([
                Repeater::make('items')
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('body')
                            ->rows(3),
                    ])
                    ->reorderableWithButtons()
                    ->collapsible()
                    ->itemLabel(fn (?array $state): ?string => $state['title'] ?? null)
                    ->columnSpanFull(),
            ]),
            Block::make('icon_list')->schema([
                Repeater::make('items')
                    ->schema([
                        TextInput::make('text')
                            ->required()
                            ->maxLength(255),
                    ])
                    ->reorderableWithButtons()
                    ->collapsible()
                    ->itemLabel(fn (?array $state): ?string => $state['text'] ?? null)
                    ->columnSpanFull(),
            ]),
            Block::make('video')->schema([
                TextInput::make('url')->required(),
                Textarea::make('caption'),
            ]),
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
        static::saving(function (self $model): void {
            $model->render_mode = $model->normalizedRenderMode($model->render_mode);
            $model->template = $model->normalizedTemplate($model->template);
            $model->hero_style = $model->normalizedHeroStyle($model->hero_style);
            $model->hero_height = $model->normalizedHeroHeight($model->hero_height);
            $model->hero_overlay = $model->normalizedHeroOverlay($model->hero_overlay);
            $model->hero_text_align = $model->normalizedHeroTextAlign($model->hero_text_align);
            $model->hero_text_width = $model->normalizedHeroTextWidth($model->hero_text_width);

            if (auth()->check() && ! auth()->user()?->can('pages.render_unsafe_html')) {
                $model->custom_html_is_trusted = false;
            }

            $model->title = $model->sanitizeCmsField($model->title);
            $model->description = $model->sanitizeCmsField($model->description);
            $model->content = $model->sanitizeCmsField($model->content);
            $model->hero_title = $model->sanitizeCmsField($model->hero_title);
            $model->hero_subtitle = $model->sanitizeCmsField($model->hero_subtitle);
            $model->hero_cta_text = $model->sanitizeCmsField($model->hero_cta_text);
            $model->content_blocks = $model->prepareContentBlocks($model->content_blocks);

            $trusted = (bool) $model->custom_html_is_trusted;
            $profile = $trusted ? 'cms_custom_html_trusted' : 'cms_custom_html_strict';
            $model->custom_html = $model->sanitizeCmsFieldByProfile($model->custom_html, $profile);
        });

        static::saved(function (self $model): void {
            $model->syncPendingSeoMeta();
        });

        static::created(function (self $model): void {
            $model->ensureCanonicalSeoMetaRow();
        });
    }

    /**
     * @return string
     */
    protected function normalizedRenderMode(?string $mode): string
    {
        $value = (string) $mode;

        if (! in_array($value, array_map(fn (PageRenderMode $case): string => $case->value, PageRenderMode::cases()), true)) {
            return PageRenderMode::Template->value;
        }

        return $value;
    }

    protected function normalizedTemplate(?string $template): string
    {
        $value = (string) $template;

        if (! in_array($value, array_map(fn (PageTemplate $case): string => $case->value, PageTemplate::cases()), true)) {
            return PageTemplate::Standard->value;
        }

        return $value;
    }

    protected function normalizedHeroStyle(?string $style): string
    {
        $value = (string) $style;
        if (! in_array($value, array_map(fn (HeroStyle $case): string => $case->value, HeroStyle::cases()), true)) {
            return HeroStyle::Contained->value;
        }

        return $value;
    }

    protected function normalizedHeroHeight(?string $height): string
    {
        $value = (string) $height;
        if (! in_array($value, array_map(fn (HeroHeight $case): string => $case->value, HeroHeight::cases()), true)) {
            return HeroHeight::H80->value;
        }

        return $value;
    }

    protected function normalizedHeroOverlay(?string $overlay): string
    {
        $value = (string) $overlay;
        if (! in_array($value, array_map(fn (HeroOverlay $case): string => $case->value, HeroOverlay::cases()), true)) {
            return HeroOverlay::Medium->value;
        }

        return $value;
    }

    protected function normalizedHeroTextAlign(?string $align): string
    {
        $value = (string) $align;
        if (! in_array($value, array_map(fn (HeroTextAlign $case): string => $case->value, HeroTextAlign::cases()), true)) {
            return HeroTextAlign::Left->value;
        }

        return $value;
    }

    protected function normalizedHeroTextWidth(?string $width): string
    {
        $value = (string) $width;
        if (! in_array($value, array_map(fn (HeroTextWidth $case): string => $case->value, HeroTextWidth::cases()), true)) {
            return HeroTextWidth::Normal->value;
        }

        return $value;
    }

    /**
     * @return array<int, string>
     */
    protected static function imageSelectOptions(): array
    {
        return Image::query()
            ->where('is_active', true)
            ->orderByDesc('id')
            ->limit(200)
            ->get(['id', 'title', 'path', 'public_url'])
            ->mapWithKeys(function (Image $image): array {
                $label = $image->title ?: $image->path ?: $image->public_url ?: 'Image #' . $image->id;

                return [$image->id => $label];
            })
            ->all();
    }

    /**
     * @param mixed $blocks
     * @return array<int, array<string, mixed>>|null
     */
    protected function prepareContentBlocks($blocks): ?array
    {
        if (! is_array($blocks)) {
            return null;
        }

        return $this->sanitizeContentBlocks($this->normalizeContentBlocks($blocks));
    }

    /**
     * @param array<int, mixed> $blocks
     * @return array<int, mixed>
     */
    protected function normalizeContentBlocks(array $blocks): array
    {
        return collect($blocks)
            ->map(function ($block) {
                if (! is_array($block) || ($block['type'] ?? null) !== 'gallery') {
                    return $block;
                }

                $data = Arr::get($block, 'data', []);
                if (! is_array($data)) {
                    return $block;
                }

                $items = Arr::get($data, 'items', []);
                if (! is_array($items)) {
                    return $block;
                }

                $data['items'] = collect($items)
                    ->map(fn ($item) => is_array($item) ? $this->normalizeGalleryItem($item) : $item)
                    ->all();

                $block['data'] = $data;

                return $block;
            })
            ->all();
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    protected function normalizeGalleryItem(array $item): array
    {
        $sourceType = (string) ($item['source_type'] ?? 'existing');

        if ($sourceType === 'upload') {
            $path = $this->normalizedUploadPath($item['upload_path'] ?? null);

            if ($path !== null) {
                $image = $this->imageFromUploadedGalleryPath($path, $item);
                $item['source_type'] = 'existing';
                $item['image_id'] = $image->id;
                unset($item['upload_path'], $item['src']);

                return $item;
            }
        }

        if ($sourceType === 'existing') {
            $item['image_id'] = filled($item['image_id'] ?? null) ? (int) $item['image_id'] : null;
            unset($item['upload_path'], $item['src']);

            return $item;
        }

        if ($sourceType === 'url') {
            unset($item['image_id'], $item['upload_path']);

            return $item;
        }

        $item['source_type'] = 'existing';
        unset($item['upload_path'], $item['src']);

        return $item;
    }

    protected function normalizedUploadPath($path): ?string
    {
        if (is_array($path)) {
            $path = collect($path)->first();
        }

        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        return ltrim($path, '/');
    }

    /**
     * @param array<string, mixed> $item
     */
    protected function imageFromUploadedGalleryPath(string $path, array $item): Image
    {
        $disk = 'public';
        $storage = Storage::disk($disk);
        $extension = pathinfo($path, PATHINFO_EXTENSION) ?: null;
        $mimeType = null;
        $sizeBytes = null;
        $width = null;
        $height = null;

        try {
            $mimeType = $storage->exists($path) ? $storage->mimeType($path) : null;
            $sizeBytes = $storage->exists($path) ? $storage->size($path) : null;
        } catch (\Throwable) {
            $mimeType = null;
            $sizeBytes = null;
        }

        try {
            $absolutePath = $storage->path($path);
            if (is_string($absolutePath) && is_file($absolutePath)) {
                $dimensions = @getimagesize($absolutePath);

                if (is_array($dimensions)) {
                    $width = $dimensions[0] ?? null;
                    $height = $dimensions[1] ?? null;
                }
            }
        } catch (\Throwable) {
            $width = null;
            $height = null;
        }

        return Image::query()->firstOrCreate(
            [
                'disk' => $disk,
                'path' => $path,
            ],
            [
                'public_url' => url('/storage/' . ltrim($path, '/')),
                'mime_type' => $mimeType,
                'extension' => $extension,
                'size_bytes' => $sizeBytes,
                'width' => $width,
                'height' => $height,
                'title' => filled($item['title'] ?? null) ? $this->sanitizeCmsField((string) $item['title']) : null,
                'alt_text' => filled($item['alt'] ?? null) ? $this->sanitizeCmsField((string) $item['alt']) : null,
                'caption' => filled($item['caption'] ?? null) ? $this->sanitizeCmsField((string) $item['caption']) : null,
                'is_decorative' => false,
                'is_active' => true,
                'created_by' => auth()->id(),
            ]
        );
    }

    /**
     * @param mixed $blocks
     * @return array<int, array<string, mixed>>|null
     */
    protected function sanitizeContentBlocks($blocks): ?array
    {
        if (! is_array($blocks)) {
            return null;
        }

        return collect($blocks)
            ->map(function ($block) {
                if (! is_array($block)) {
                    return null;
                }

                $type = (string) ($block['type'] ?? '');
                if ($type === '') {
                    return null;
                }

                $data = Arr::get($block, 'data', []);
                if (! is_array($data)) {
                    $data = [];
                }

                return [
                    'type' => $type,
                    'data' => $this->sanitizeContentBlockValue($data),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    protected function sanitizeContentBlockValue($value)
    {
        if (is_string($value)) {
            return $this->sanitizeCmsField($value);
        }

        if (is_array($value)) {
            return collect($value)
                ->map(fn ($child) => $this->sanitizeContentBlockValue($child))
                ->all();
        }

        return $value;
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

    protected function ensureCanonicalSeoMetaRow(): void
    {
        if (! $this->exists || ! $this->language_id) {
            return;
        }

        $this->seoMeta()->updateOrCreate(
            [
                'target_key' => '',
                'language_id' => $this->language_id,
            ],
            [
                'seo_title' => null,
                'seo_description' => null,
                'seo_og_image' => null,
                'is_active' => true,
            ]
        );
    }


    /**
     * Scope Get Translation with Page by Slug
     *
     * @param  mixed $query
     * @return void
     */
    public function scopeTranslationBySlug(EloquentBuilder $query, $slug)
    {
        $query->with('page')->where('slug', $slug);
    }


    /**
     * Scope Get Active Translation and Page
     *
     * @param  mixed $query
     * @return void
     */
    public function scopeActiveTranslation(EloquentBuilder $query)
    {
        $query->where('is_active', true);
    }


    /**
     * Scope Get Translation by Language
     *
     * @param  mixed $query
     * @return void
     */
    public function scopeByLanguage(EloquentBuilder $query, $language)
    {
        $query->where( 'language_id', $language);
    }
}
