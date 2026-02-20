<?php

namespace App\Services\Seo;

use App\Models\Language;
use App\Models\SeoMeta;
use Illuminate\Database\Eloquent\Model;

class SeoMetaResolver
{
    /**
     * @param array{
     *   title?: string|null,
     *   description?: string|null,
     *   og_image?: string|null,
     *   canonical_path?: string|null,
     *   robots?: string|null
     * } $fallback
     * @return array{
     *   metaTitle: string,
     *   metaDescription: string,
     *   ogImage: string|null,
     *   canonicalPath: string|null,
     *   robots: string
     * }
     */
    public function forModel(Model $model, ?int $languageId = null, array $fallback = []): array
    {
        $record = $this->resolveRecord([
            'seoable_type' => $model->getMorphClass(),
            'seoable_id' => (int) $model->getKey(),
            'target_key' => '',
        ], $languageId);

        return $this->payloadFromRecord($record, $fallback);
    }

    /**
     * @param array{
     *   title?: string|null,
     *   description?: string|null,
     *   og_image?: string|null,
     *   canonical_path?: string|null,
     *   robots?: string|null
     * } $fallback
     * @return array{
     *   metaTitle: string,
     *   metaDescription: string,
     *   ogImage: string|null,
     *   canonicalPath: string|null,
     *   robots: string
     * }
     */
    public function forRoute(string $routeKey, ?int $languageId = null, array $fallback = []): array
    {
        $record = $this->resolveRecord([
            'seoable_type' => 'route',
            'seoable_id' => 0,
            'target_key' => $routeKey,
        ], $languageId);

        return $this->payloadFromRecord($record, $fallback);
    }

    /**
     * @param array{seoable_type:string,seoable_id:int,target_key:string} $target
     */
    protected function resolveRecord(array $target, ?int $languageId = null): ?SeoMeta
    {
        $records = SeoMeta::query()
            ->where('seoable_type', $target['seoable_type'])
            ->where('seoable_id', $target['seoable_id'])
            ->where('target_key', $target['target_key'])
            ->where('is_active', true)
            ->get();

        if ($records->isEmpty()) {
            return null;
        }

        $currentLanguageId = $languageId
            ?: session('language_id')
            ?: Language::query()->where('locale', app()->getLocale())->value('id');

        if ($currentLanguageId) {
            $current = $records->firstWhere('language_id', $currentLanguageId);
            if ($current) {
                return $current;
            }
        }

        $defaultLanguageId = Language::query()->orderBy('id')->value('id');
        if ($defaultLanguageId) {
            $default = $records->firstWhere('language_id', $defaultLanguageId);
            if ($default) {
                return $default;
            }
        }

        return $records->first();
    }

    /**
     * @param array{
     *   title?: string|null,
     *   description?: string|null,
     *   og_image?: string|null,
     *   canonical_path?: string|null,
     *   robots?: string|null
     * } $fallback
     * @return array{
     *   metaTitle: string,
     *   metaDescription: string,
     *   ogImage: string|null,
     *   canonicalPath: string|null,
     *   robots: string
     * }
     */
    protected function payloadFromRecord(?SeoMeta $record, array $fallback): array
    {
        return [
            'metaTitle' => (string) ($record?->seo_title ?: ($fallback['title'] ?? config('seo.default_title'))),
            'metaDescription' => (string) ($record?->seo_description ?: ($fallback['description'] ?? config('seo.default_description'))),
            'ogImage' => $record?->seo_og_image ?: ($fallback['og_image'] ?? null),
            'canonicalPath' => $record?->canonical_path ?: ($fallback['canonical_path'] ?? null),
            'robots' => (string) ($record?->robots ?: ($fallback['robots'] ?? config('seo.robots.indexable', 'index,follow'))),
        ];
    }
}
