<?php

namespace App\Services\Media;

use App\Models\Language;
use App\Models\PageTranslation;
use App\Models\Video;
use App\Models\Videoable;

class VideoResolver
{
    /**
     * @return array{url:string,source_type:string,title:?string,description:?string,poster_url:?string,is_decorative:bool,source:string,role:string,video_id:int}|null
     */
    public function resolveForTranslation(?PageTranslation $translation, string $role): ?array
    {
        if (! $translation || ! $translation->page) {
            return null;
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
                $assignment = Videoable::query()
                    ->where('videoable_type', PageTranslation::class)
                    ->where('videoable_id', $candidate->id)
                    ->where('role', $candidateRole)
                    ->where('is_active', true)
                    ->with(['video.posterImage'])
                    ->first();

                $video = $assignment?->video;
                if ($video && $video->is_active) {
                    return $this->toResolved($video, 'role', $candidateRole);
                }
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
            'hero_video' => ['hero_video', 'featured_video'],
            default => [$role],
        };
    }

    /**
     * @return array{url:string,source_type:string,title:?string,description:?string,poster_url:?string,is_decorative:bool,source:string,role:string,video_id:int}
     */
    private function toResolved(Video $video, string $source, string $role): array
    {
        return [
            'url' => $video->resolvedUrl(),
            'source_type' => (string) $video->source_type,
            'title' => $video->title,
            'description' => $video->description,
            'poster_url' => $video->posterImage?->resolvedUrl(),
            'is_decorative' => (bool) $video->is_decorative,
            'source' => $source,
            'role' => $role,
            'video_id' => $video->id,
        ];
    }
}
