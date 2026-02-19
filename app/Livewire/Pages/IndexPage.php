<?php

namespace App\Livewire\Pages;

use App\Models\Language;
use App\Models\Page;
use App\Models\RouteSeo;
use App\Services\Seo\RouteSeoResolver;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Illuminate\Support\Collection;

class IndexPage extends Component
{
    /**
     * Used to bust computed cache when language changes.
     * Livewire computed props are cached per component render.
     */
    public int $refreshKey = 0;

    #[On('language-switched')]
    public function onLanguageSwitched(): void
    {
        $this->refreshKey++;
    }

    /**
     * Items for the list:
     * [
     *   'translation'  => PageTranslation,
     *   'english_only' => bool
     * ]
     */
    #[Computed]
    public function items(): Collection
    {
        // Make computed depend on refreshKey so it recalculates on language switch
        $this->refreshKey;

        // Current language (session) or fallback to first language row.
        $currentLanguage = Language::find(session('language_id')) ?? Language::first();
        $defaultLanguage = Language::first(); // English in your seeder

        // All active pages with at least one active translation,
        // eager-loaded as active-only via your scope.
        $pages = Page::allActivePagesWithAnyActiveTranslation()->get();

        return $pages
            ->map(function (Page $page) use ($currentLanguage, $defaultLanguage) {
                $activeTranslations = $page->pageTranslations;

                // 1) Translation in current language
                if ($currentLanguage) {
                    $currentTx = $activeTranslations->firstWhere('language_id', $currentLanguage->id);

                    if ($currentTx) {
                        return [
                            'translation'  => $currentTx,
                            'english_only' => false,
                        ];
                    }
                }

                // 2) Fallback to default (English)
                if ($defaultLanguage) {
                    $defaultTx = $activeTranslations->firstWhere('language_id', $defaultLanguage->id);

                    if ($defaultTx) {
                        return [
                            'translation'  => $defaultTx,
                            'english_only' => true,
                        ];
                    }
                }

                // 3) Final fallback: any active translation
                $anyTx = $activeTranslations->first();

                return [
                    'translation'  => $anyTx,
                    'english_only' => false,
                ];
            })
            // ultra safety: drop any null translations
            ->filter(fn ($item) => ! empty($item['translation']))
            ->values();
    }

    public function render()
    {
        $seo = app(RouteSeoResolver::class)->resolve(RouteSeo::ROUTE_PAGES_INDEX);

        return view('livewire.pages.index-page', [
            'items' => $this->items,
        ])->layout('components.layouts.app', [
            'title' => $seo['title'],
            'metaTitle' => $seo['metaTitle'],
            'metaDescription' => $seo['metaDescription'],
            'canonicalUrl' => $seo['canonicalUrl'],
            'ogType' => $seo['ogType'],
            'ogTitle' => $seo['ogTitle'],
            'ogDescription' => $seo['ogDescription'],
            'ogImage' => $seo['ogImage'],
            'twitterTitle' => $seo['twitterTitle'],
            'twitterDescription' => $seo['twitterDescription'],
            'twitterImage' => $seo['twitterImage'],
            'seoJsonLd' => [
                [
                    '@context' => 'https://schema.org',
                    '@type' => 'CollectionPage',
                    'name' => $seo['metaTitle'],
                    'description' => $seo['metaDescription'],
                    'url' => $seo['canonicalUrl'],
                ],
            ],
        ]);
    }
}
