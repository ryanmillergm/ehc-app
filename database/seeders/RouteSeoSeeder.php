<?php

namespace Database\Seeders;

use App\Models\Language;
use App\Models\SeoMeta;
use App\Services\Seo\RouteSeoResolver;
use App\Support\Seo\RouteSeoTarget;
use Illuminate\Database\Seeder;

class RouteSeoSeeder extends Seeder
{
    public function run(): void
    {
        $languages = Language::query()->pluck('id');
        if ($languages->isEmpty()) {
            return;
        }

        $resolver = app(RouteSeoResolver::class);

        foreach (array_keys(RouteSeoTarget::options()) as $routeKey) {
            $resolved = $resolver->resolve($routeKey);

            foreach ($languages as $languageId) {
                SeoMeta::query()->updateOrCreate(
                    [
                        'seoable_type' => 'route',
                        'seoable_id' => 0,
                        'target_key' => $routeKey,
                        'language_id' => $languageId,
                    ],
                    [
                        'seo_title' => $resolved['metaTitle'],
                        'seo_description' => $resolved['metaDescription'],
                        'seo_og_image' => $resolved['ogImage'],
                        'canonical_path' => parse_url($resolved['canonicalUrl'], PHP_URL_PATH) ?: '/',
                        'robots' => $resolved['metaRobots'],
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
