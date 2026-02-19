<?php

namespace App\Services\Seo;

use App\Models\Language;
use App\Models\RouteSeo;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;

class RouteSeoResolver
{
    /**
     * @return array{
     *   title: string,
     *   metaTitle: string,
     *   metaDescription: string,
     *   canonicalUrl: string,
     *   ogType: string,
     *   ogTitle: string,
     *   ogDescription: string,
     *   ogImage: string|null,
     *   twitterTitle: string,
     *   twitterDescription: string,
     *   twitterImage: string|null
     * }
     */
    public function resolve(string $routeKey): array
    {
        $defaults = Arr::get($this->defaults(), $routeKey, []);
        $record = $this->resolveRecord($routeKey);

        $title = (string) ($record?->seo_title ?: ($defaults['seo_title'] ?? config('seo.default_title')));
        $description = (string) ($record?->seo_description ?: ($defaults['seo_description'] ?? config('seo.default_description')));

        $canonicalPath = $record?->canonical_path ?: ($defaults['canonical_path'] ?? null);
        $canonicalUrl = $this->canonicalUrl($routeKey, $canonicalPath);

        $ogImage = $record?->seo_og_image;
        if (blank($ogImage)) {
            $defaultOgImage = config('seo.default_og_image');
            $ogImage = str_starts_with((string) $defaultOgImage, 'http')
                ? (string) $defaultOgImage
                : asset(ltrim((string) $defaultOgImage, '/'));
        } elseif (! str_starts_with((string) $ogImage, 'http')) {
            $ogImage = asset(ltrim((string) $ogImage, '/'));
        }

        return [
            'title' => $title,
            'metaTitle' => $title,
            'metaDescription' => $description,
            'canonicalUrl' => $canonicalUrl,
            'ogType' => 'website',
            'ogTitle' => $title,
            'ogDescription' => $description,
            'ogImage' => $ogImage,
            'twitterTitle' => $title,
            'twitterDescription' => $description,
            'twitterImage' => $ogImage,
        ];
    }

    protected function resolveRecord(string $routeKey): ?RouteSeo
    {
        if (! array_key_exists($routeKey, RouteSeo::routeOptions())) {
            return null;
        }

        $records = RouteSeo::query()
            ->active()
            ->forRouteKey($routeKey)
            ->get();

        if ($records->isEmpty()) {
            return null;
        }

        $currentLanguage = Language::find(session('language_id'))
            ?? Language::query()->where('locale', app()->getLocale())->first();
        $defaultLanguage = Language::first();

        if ($currentLanguage) {
            $current = $records->firstWhere('language_id', $currentLanguage->id);
            if ($current) {
                return $current;
            }
        }

        if ($defaultLanguage) {
            $default = $records->firstWhere('language_id', $defaultLanguage->id);
            if ($default) {
                return $default;
            }
        }

        return $records->first();
    }

    protected function canonicalUrl(string $routeKey, ?string $canonicalPath): string
    {
        if (Route::has($routeKey)) {
            return route($routeKey);
        }

        if (filled($canonicalPath)) {
            return url($canonicalPath);
        }

        return url()->current();
    }

    /**
     * @return array<string, array{seo_title: string, seo_description: string, canonical_path: string}>
     */
    protected function defaults(): array
    {
        return [
            RouteSeo::ROUTE_DONATIONS_SHOW => [
                'seo_title' => 'Give to Support Homeless Outreach in Sacramento | Bread of Grace Ministries',
                'seo_description' => 'Support Bread of Grace Ministries through one-time or monthly giving. Your donation helps provide meals, essentials, and Christ-centered outreach in Sacramento.',
                'canonical_path' => '/give',
            ],
            RouteSeo::ROUTE_PAGES_INDEX => [
                'seo_title' => 'Community Outreach Pages | Bread of Grace Ministries',
                'seo_description' => 'Explore Bread of Grace Ministries pages on outreach, discipleship, and ways to serve and give in Sacramento.',
                'canonical_path' => '/pages',
            ],
            RouteSeo::ROUTE_EMAILS_SUBSCRIBE => [
                'seo_title' => 'Subscribe for Ministry Updates | Bread of Grace Ministries',
                'seo_description' => 'Subscribe for outreach updates, stories, and opportunities to serve with Bread of Grace Ministries.',
                'canonical_path' => '/emails/subscribe',
            ],
        ];
    }
}
