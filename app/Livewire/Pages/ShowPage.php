<?php

namespace App\Livewire\Pages;

use App\Models\Language;
use App\Models\PageTranslation;
use Livewire\Attributes\On;
use Livewire\Component;

class ShowPage extends Component
{
    public ?PageTranslation $translation = null;

    /** Stable slug from the URL we mounted with */
    public string $slug;

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

        if (! $slugTx || ! $slugTx->is_active || ! $slugTx->page?->is_active) {
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
                ->where('is_active', true)
                ->first();

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
        return view('livewire.pages.show-page', [
            'translation' => $this->translation,
        ]);
    }
}
