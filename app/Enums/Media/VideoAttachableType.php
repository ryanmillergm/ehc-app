<?php

namespace App\Enums\Media;

use App\Models\HomePageContent;
use App\Models\PageTranslation;

enum VideoAttachableType: string
{
    case PageTranslation = PageTranslation::class;
    case HomePageContent = HomePageContent::class;

    public function label(): string
    {
        return match ($this) {
            self::PageTranslation => 'Page Translation',
            self::HomePageContent => 'Home Page Content',
        };
    }

    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    public static function labelFor(?string $modelClass): ?string
    {
        return self::tryFrom((string) $modelClass)?->label() ?? $modelClass;
    }

    public static function relatedRecordOptions(?string $modelClass, int $limit = 200): array
    {
        $case = self::tryFrom((string) $modelClass);

        if (! $case) {
            return [];
        }

        return match ($case) {
            self::PageTranslation => PageTranslation::query()
                ->orderByDesc('id')
                ->limit($limit)
                ->get()
                ->mapWithKeys(fn (PageTranslation $translation): array => [
                    $translation->id => "#{$translation->id} - {$translation->title} ({$translation->slug})",
                ])
                ->all(),
            self::HomePageContent => HomePageContent::query()
                ->with('language')
                ->orderByDesc('id')
                ->limit($limit)
                ->get()
                ->mapWithKeys(function (HomePageContent $content): array {
                    $language = $content->language?->title ?? 'No language';
                    $title = filled($content->seo_title) ? $content->seo_title : 'Untitled';

                    return [
                        $content->id => "#{$content->id} - {$language}: {$title}",
                    ];
                })
                ->all(),
        };
    }

    public static function targetExists(?string $modelClass, int $id): bool
    {
        if ($id < 1) {
            return false;
        }

        $case = self::tryFrom((string) $modelClass);

        if (! $case) {
            return false;
        }

        return match ($case) {
            self::PageTranslation => PageTranslation::query()->whereKey($id)->exists(),
            self::HomePageContent => HomePageContent::query()->whereKey($id)->exists(),
        };
    }
}
