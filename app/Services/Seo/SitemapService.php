<?php

namespace App\Services\Seo;

use App\Models\PageTranslation;
use Illuminate\Support\Collection;

class SitemapService
{
    /**
     * @return Collection<int, array{loc: string, lastmod: ?string}>
     */
    public function urls(): Collection
    {
        $baseUrl = rtrim(config('app.url'), '/');

        $static = collect([
            ['loc' => $baseUrl . '/', 'lastmod' => null],
            ['loc' => $baseUrl . '/give', 'lastmod' => null],
            ['loc' => $baseUrl . '/emails/subscribe', 'lastmod' => null],
            ['loc' => $baseUrl . '/pages', 'lastmod' => null],
        ]);

        $pages = PageTranslation::query()
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            })
            ->whereHas('page', fn ($query) => $query->where('is_active', true))
            ->get(['slug', 'updated_at'])
            ->map(function (PageTranslation $translation) use ($baseUrl) {
                return [
                    'loc' => $baseUrl . '/pages/' . ltrim($translation->slug, '/'),
                    'lastmod' => optional($translation->updated_at)->toAtomString(),
                ];
            });

        return $static
            ->merge($pages)
            ->unique('loc')
            ->values();
    }
}
