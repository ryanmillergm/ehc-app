<?php

namespace App\Services\Seo;

use App\Support\Seo\RouteSeoTarget;
use Illuminate\Support\Facades\Route;

class RouteSeoResolver
{
    public function __construct(
        private readonly SeoMetaResolver $seoMetaResolver,
    ) {
    }

    /**
     * @return array{
     *   title: string,
     *   metaTitle: string,
     *   metaDescription: string,
     *   metaRobots: string,
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
        $defaults = $this->defaults()[$routeKey] ?? [];
        $resolved = $this->seoMetaResolver->forRoute($routeKey, null, [
            'title' => $defaults['seo_title'] ?? config('seo.default_title'),
            'description' => $defaults['seo_description'] ?? config('seo.default_description'),
            'canonical_path' => $defaults['canonical_path'] ?? null,
            'og_image' => config('seo.default_og_image'),
        ]);

        $title = $resolved['metaTitle'];
        $description = $resolved['metaDescription'];
        $canonicalPath = $resolved['canonicalPath'];
        $canonicalUrl = $this->canonicalUrl($routeKey, $canonicalPath);

        $ogImage = $resolved['ogImage'];
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
            'metaRobots' => $resolved['robots'],
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

    protected function canonicalUrl(string $routeKey, ?string $canonicalPath): string
    {
        if (filled($canonicalPath)) {
            return url($canonicalPath);
        }

        if (Route::has($routeKey)) {
            return route($routeKey);
        }

        return url()->current();
    }

    /**
     * @return array<string, array{seo_title: string, seo_description: string, canonical_path: string}>
     */
    protected function defaults(): array
    {
        return [
            RouteSeoTarget::DONATIONS_SHOW => [
                'seo_title' => 'Give to Support Homeless Outreach in Sacramento | Bread of Grace Ministries',
                'seo_description' => 'Support Bread of Grace Ministries through one-time or monthly giving. Your donation helps provide meals, essentials, and Christ-centered outreach in Sacramento.',
                'canonical_path' => '/give',
            ],
            RouteSeoTarget::PAGES_INDEX => [
                'seo_title' => 'Community Outreach Pages | Bread of Grace Ministries',
                'seo_description' => 'Explore Bread of Grace Ministries pages on outreach, discipleship, and ways to serve and give in Sacramento.',
                'canonical_path' => '/pages',
            ],
            RouteSeoTarget::EMAILS_SUBSCRIBE => [
                'seo_title' => 'Subscribe for Ministry Updates | Bread of Grace Ministries',
                'seo_description' => 'Subscribe for outreach updates, stories, and opportunities to serve with Bread of Grace Ministries.',
                'canonical_path' => '/emails/subscribe',
            ],
        ];
    }
}
