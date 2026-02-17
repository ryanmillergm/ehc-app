<?php

namespace App\Services\Media;

use App\Models\Image;
use App\Models\Imageable;
use App\Models\Language;
use App\Models\PageTranslation;
use App\Models\SiteMediaDefault;

class ImageResolver
{
    /**
     * @return array{url:string,alt:?string,caption:?string,title:?string,description:?string,is_decorative:bool,source:string,role:string,image_id:int}|null
     */
    public function resolveForTranslation(?PageTranslation $translation, string $role): ?array
    {
        if (! $translation || ! $translation->page) {
            return $this->resolveGlobalFallback($role);
        }

        $candidateTranslations = [$translation];

        $defaultLanguageId = Language::query()->orderBy('id')->value('id');
        if ($defaultLanguageId && $translation->language_id !== (int) $defaultLanguageId) {
            $defaultTranslation = $translation->page
                ->pageTranslations()
                ->where('language_id', $defaultLanguageId)
                ->where('is_active', true)
                ->first();

            if ($defaultTranslation) {
                $candidateTranslations[] = $defaultTranslation;
            }
        }

        foreach ($candidateTranslations as $candidate) {
            foreach ($this->roleChain($role) as $candidateRole) {
                $assignment = Imageable::query()
                    ->where('imageable_type', PageTranslation::class)
                    ->where('imageable_id', $candidate->id)
                    ->where('role', $candidateRole)
                    ->where('is_active', true)
                    ->with('image')
                    ->first();

                $image = $assignment?->image;
                if ($image && $image->is_active) {
                    return $this->toResolved($image, 'role', $candidateRole);
                }
            }
        }

        return $this->resolveGlobalFallback($role);
    }

    /**
     * @return array{url:string,alt:?string,caption:?string,title:?string,description:?string,is_decorative:bool,source:string,role:string,image_id:int}|null
     */
    public function resolveGlobalFallback(string $role): ?array
    {
        foreach ($this->roleChain($role) as $candidateRole) {
            $default = SiteMediaDefault::query()
                ->where('role', $candidateRole)
                ->with('image')
                ->first();

            $image = $default?->image;
            if ($image && $image->is_active) {
                return $this->toResolved($image, 'site_default', $candidateRole);
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function roleChain(string $role): array
    {
        return match ($role) {
            'header' => ['header', 'featured'],
            'og' => ['og', 'featured'],
            'thumbnail' => ['thumbnail', 'featured'],
            default => [$role],
        };
    }

    /**
     * @return array{url:string,alt:?string,caption:?string,title:?string,description:?string,is_decorative:bool,source:string,role:string,image_id:int}
     */
    private function toResolved(Image $image, string $source, string $role): array
    {
        return [
            'url' => $image->resolvedUrl(),
            'alt' => $image->alt_text,
            'caption' => $image->caption,
            'title' => $image->title,
            'description' => $image->description,
            'is_decorative' => (bool) $image->is_decorative,
            'source' => $source,
            'role' => $role,
            'image_id' => $image->id,
        ];
    }
}
