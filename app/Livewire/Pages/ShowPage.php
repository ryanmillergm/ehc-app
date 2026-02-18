<?php

namespace App\Livewire\Pages;

use App\Models\Language;
use App\Models\PageTranslation;
use App\Services\Media\ImageResolver;
use App\Services\Media\VideoResolver;
use Livewire\Attributes\On;
use Livewire\Component;

class ShowPage extends Component
{
    public ?PageTranslation $translation = null;

    /** Stable slug from the URL we mounted with */
    public string $slug;

    protected array $allowedTemplates = ['standard', 'campaign', 'story'];

    public function mount(string $slug): void
    {
        $this->slug = $slug;

        $resolved = $this->resolveTranslation($slug);

        if (! $resolved) {
            // If the slug is truly invalid or page inactive, bounce to index
            $this->redirect('/pages', navigate: true);
            return;
        }

        $this->translation = $resolved;
    }

    /**
     * Decide which translation to display (no redirect).
     * Returns null ONLY if slug doesn't exist / inactive.
     */
    protected function resolveTranslation(string $slug): ?PageTranslation
    {
        $slugTx = PageTranslation::translationBySlug($slug)->first();

        if (! $this->isPublicTranslation($slugTx) || ! $slugTx->page?->is_active) {
            session()->flash('status', "Sorry. The page '{$slug}' does not exist.");
            return null;
        }

        $page = $slugTx->page;

        $currentLanguage =
            Language::find(session('language_id'))
            ?? Language::where('locale', app()->getLocale())->first()
            ?? Language::first();

        $defaultLanguage = Language::first(); // English in your seeder

        $activeFor = fn (Language $lang) =>
            $page->pageTranslations
                ->where('language_id', $lang->id)
                ->first(fn (PageTranslation $translation) => $this->isPublicTranslation($translation));

        // 1) Slug matches current language? show it.
        if ($slugTx->language_id === $currentLanguage->id) {
            return $slugTx;
        }

        // 2) Show current language if exists
        if ($currentTx = $activeFor($currentLanguage)) {
            session()->flash('status', "Showing {$currentLanguage->title} version.");
            return $currentTx;
        }

        // 3) Otherwise default (English) if exists
        if ($defaultTx = $activeFor($defaultLanguage)) {
            session()->flash(
                'status',
                "{$currentLanguage->title} version not available. Showing {$defaultLanguage->title} instead."
            );
            return $defaultTx;
        }

        // 4) Last fallback: show the slug translation
        $slugLang = Language::find($slugTx->language_id);
        session()->flash('status', "Showing {$slugLang?->title} version.");
        return $slugTx;
    }

    protected function isPublicTranslation(?PageTranslation $translation): bool
    {
        if (! $translation || ! $translation->is_active) {
            return false;
        }

        if (! $translation->published_at) {
            return true;
        }

        return $translation->published_at->lte(now());
    }

    /**
     * Fired by Navbar::switchLanguage().
     * Re-resolves in place (no full reload).
     */
    #[On('language-switched')]
    public function onLanguageSwitched(?string $code = null): void
    {
        if ($resolved = $this->resolveTranslation($this->slug)) {
            $this->translation = $resolved;
        }
    }

    public function render()
    {
        $template = $this->resolvedTemplate();
        $metaTitle = $this->translation?->seo_title ?: $this->translation?->title;
        $metaDescription = $this->translation?->seo_description ?: $this->translation?->description;
        $canonicalUrl = $this->translation ? url('/pages/' . $this->translation->slug) : url('/pages');
        $ogImage = $this->translation?->seo_og_image ?: $this->resolvedOgImage();

        return view('livewire.pages.show-page', [
            'translation' => $this->translation,
            'template' => $template,
            'pageView' => $this->buildPageView($template),
        ])->layout('components.layouts.app', [
            'title' => $metaTitle ?: 'Page',
            'metaTitle' => $metaTitle ?: 'Page',
            'metaDescription' => $metaDescription ?: 'Page details',
            'canonicalUrl' => $canonicalUrl,
            'ogTitle' => $metaTitle ?: 'Page',
            'ogDescription' => $metaDescription ?: 'Page details',
            'ogImage' => $ogImage,
            'twitterTitle' => $metaTitle ?: 'Page',
            'twitterDescription' => $metaDescription ?: 'Page details',
            'twitterImage' => $ogImage,
        ]);
    }

    protected function resolvedTemplate(): string
    {
        $template = (string) ($this->translation?->template ?? 'standard');

        return in_array($template, $this->allowedTemplates, true)
            ? $template
            : 'standard';
    }

    protected function resolvedOgImage(): ?string
    {
        if (! $this->translation) {
            return null;
        }

        /** @var ImageResolver $resolver */
        $resolver = app(ImageResolver::class);

        $resolved = $resolver->resolveForTranslation($this->translation, 'og')
            ?: $resolver->resolveForTranslation($this->translation, 'header');

        return $resolved['url'] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildPageView(string $template): array
    {
        if (! $this->translation) {
            return [];
        }

        /** @var ImageResolver $imageResolver */
        $imageResolver = app(ImageResolver::class);
        /** @var VideoResolver $videoResolver */
        $videoResolver = app(VideoResolver::class);

        $heroImage = $imageResolver->resolveForTranslation($this->translation, 'header');
        $heroVideo = $videoResolver->resolveForTranslation($this->translation, 'hero_video');

        $sliderGroup = $this->translation
            ->imageGroupables()
            ->where('role', 'hero_slider')
            ->where('is_active', true)
            ->with('group.items.image')
            ->first();

        $heroSlides = collect($sliderGroup?->group?->items ?? [])
            ->filter(fn ($item) => (bool) $item->is_active && $item->image)
            ->map(fn ($item) => [
                'url' => $item->image->resolvedUrl(),
                'alt' => $item->image->alt_text,
                'title' => $item->image->title,
            ])
            ->values()
            ->all();

        $heroMode = (string) ($this->translation->hero_mode ?? 'none');

        if ($heroMode === 'slider' && count($heroSlides) < 1) {
            $heroMode = $heroImage ? 'image' : 'none';
        }

        if ($heroMode === 'video' && ! $heroVideo) {
            $heroMode = $heroImage ? 'image' : 'none';
        }

        if ($heroMode === 'image' && ! $heroImage) {
            $heroMode = 'none';
        }

        return [
            'template' => $template,
            'theme' => (string) ($this->translation->theme ?: 'default'),
            'hero_mode' => $heroMode,
            'hero_title' => $this->translation->hero_title ?: $this->translation->title,
            'hero_subtitle' => $this->translation->hero_subtitle ?: $this->translation->description,
            'hero_cta_text' => $this->translation->hero_cta_text,
            'hero_cta_url' => $this->translation->hero_cta_url,
            'hero_image' => $heroImage,
            'hero_video' => $heroVideo,
            'hero_slides' => $heroSlides,
            'layout_data' => $this->translation->layout_data ?: [],
            'title' => $this->translation->title,
            'description' => $this->translation->description,
            'content' => $this->translation->content,
            'right_to_left' => (bool) optional($this->translation->language)->right_to_left,
        ];
    }
}
