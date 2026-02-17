<?php

namespace App\Services\Content;

use App\Models\FaqItem;
use App\Models\HomePageContent;
use App\Models\Language;
use App\Services\Media\ImageResolver;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class HomeContentService
{
    public function __construct(
        private readonly ImageResolver $imageResolver,
    ) {
    }

    /**
     * @return array{
     *   seoTitle:string,
     *   seoDescription:string,
     *   heroIntro:string,
     *   meetingSchedule:string,
     *   meetingLocation:string,
     *   faqItems:Collection<int, mixed>,
     *   images:array<string,string>
     * }
     */
    public function build(): array
    {
        $language = $this->currentLanguage();
        $defaultLanguage = Language::query()->orderBy('id')->first();

        $content = HomePageContent::query()
            ->where('is_active', true)
            ->where('language_id', $language?->id)
            ->with(['heroImage', 'featuredImage', 'ogImage'])
            ->first();

        if (! $content && $defaultLanguage) {
            $content = HomePageContent::query()
                ->where('is_active', true)
                ->where('language_id', $defaultLanguage->id)
                ->with(['heroImage', 'featuredImage', 'ogImage'])
                ->first();
        }

        $faqItems = $this->faqForLanguage($language?->id);
        if ($faqItems->isEmpty() && $defaultLanguage) {
            $faqItems = $this->faqForLanguage($defaultLanguage->id);
        }
        if ($faqItems->isEmpty()) {
            $faqItems = $this->defaultFaqItems();
        }

        return [
            'seoTitle' => $content?->seo_title ?: 'Homeless Ministry in Sacramento, CA | Bread of Grace Ministries',
            'seoDescription' => $content?->seo_description ?: 'Bread of Grace Ministries serves Sacramento through homeless outreach, hot meals, housing pathways, discipleship, and practical support. Give to help feed the hungry and support the needy.',
            'heroIntro' => $content?->hero_intro ?: 'As a homeless ministry in Sacramento, California, we feed the hungry, help the needy, and walk with people through Christ-centered mentorship, practical support, and pathways to stable housing.',
            'meetingSchedule' => $content?->meeting_schedule ?: 'Thursday + Sunday • 11:00am',
            'meetingLocation' => $content?->meeting_location ?: 'Township 9 Park • Sacramento',
            'faqItems' => $faqItems,
            'images' => [
                'hero_primary' => $content?->heroImage?->resolvedUrl() ?: asset('images/sm/the-mayor.jpg'),
                'hero_secondary' => $content?->featuredImage?->resolvedUrl() ?: asset('images/sm/lisa-hug.jpg'),
                'seo_og' => $content?->ogImage?->resolvedUrl()
                    ?: ($this->imageResolver->resolveGlobalFallback('og')['url'] ?? asset('images/sm/the-mayor.jpg')),
            ],
        ];
    }

    /**
     * @return Collection<int, FaqItem>
     */
    private function faqForLanguage(?int $languageId): Collection
    {
        if (! $languageId) {
            return collect();
        }

        return FaqItem::query()
            ->where('context', 'home')
            ->where('is_active', true)
            ->where(function ($query) use ($languageId) {
                $query->where('language_id', $languageId)
                    ->orWhereNull('language_id');
            })
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * @return Collection<int, object>
     */
    private function defaultFaqItems(): Collection
    {
        return collect([
            [
                'question' => 'How are donations used?',
                'answer' => 'Donations support outreach essentials including hot meals, hygiene and survival supplies, bibles, discipleship, and practical housing and employment support.',
            ],
            [
                'question' => 'Where does outreach happen?',
                'answer' => 'Outreach gatherings are held in Sacramento at Township 9 Park every Thursday and Sunday at 11:00am.',
            ],
            [
                'question' => 'Can I volunteer if I am new?',
                'answer' => 'Yes. New volunteers are welcome and can help with food service, outreach support, prayer, and follow-up care.',
            ],
            [
                'question' => 'Can I give monthly to support long-term impact?',
                'answer' => 'Yes. Monthly giving helps sustain consistent ministry work with food outreach, mentorship, and future housing support.',
            ],
        ])->map(fn (array $item) => (object) Arr::only($item, ['question', 'answer']));
    }

    private function currentLanguage(): ?Language
    {
        return Language::query()->find(session('language_id'))
            ?: Language::query()->where('locale', app()->getLocale())->first()
            ?: Language::query()->orderBy('id')->first();
    }
}
