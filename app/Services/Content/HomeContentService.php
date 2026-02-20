<?php

namespace App\Services\Content;

use App\Enums\HomeSectionKey;
use App\Models\FaqItem;
use App\Models\HomePageContent;
use App\Models\Image;
use App\Models\HomeSection;
use App\Models\Language;
use App\Services\Media\ImageResolver;
use App\Services\Seo\SeoMetaResolver;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class HomeContentService
{
    public function __construct(
        private readonly ImageResolver $imageResolver,
        private readonly SeoMetaResolver $seoMetaResolver,
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
     *   images:array<string,string>,
     *   sections:array<string,mixed>
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

        $sections = $this->sectionsForLanguage($language?->id);
        if ($sections->isEmpty() && $defaultLanguage) {
            $sections = $this->sectionsForLanguage($defaultLanguage->id);
        }

        $faqItems = $this->faqForLanguage($language?->id);
        if ($faqItems->isEmpty() && $defaultLanguage) {
            $faqItems = $this->faqForLanguage($defaultLanguage->id);
        }
        if ($faqItems->isEmpty()) {
            $faqItems = $this->defaultFaqItems();
        }

        $sectionPayload = $this->buildSectionPayload($sections, $content);
        $seo = $content
            ? $this->seoMetaResolver->forModel($content, $language?->id, [
                'title' => 'Homeless Ministry in Sacramento, CA | Bread of Grace Ministries',
                'description' => 'Bread of Grace Ministries serves Sacramento through homeless outreach, hot meals, housing pathways, discipleship, and practical support. Give to help feed the hungry and support the needy.',
            ])
            : [
                'metaTitle' => 'Homeless Ministry in Sacramento, CA | Bread of Grace Ministries',
                'metaDescription' => 'Bread of Grace Ministries serves Sacramento through homeless outreach, hot meals, housing pathways, discipleship, and practical support. Give to help feed the hungry and support the needy.',
            ];

        return [
            'seoTitle' => (string) ($seo['metaTitle'] ?? 'Homeless Ministry in Sacramento, CA | Bread of Grace Ministries'),
            'seoDescription' => (string) ($seo['metaDescription'] ?? 'Bread of Grace Ministries serves Sacramento through homeless outreach, hot meals, housing pathways, discipleship, and practical support. Give to help feed the hungry and support the needy.'),
            'heroIntro' => $sectionPayload['hero']['intro'],
            'meetingSchedule' => $sectionPayload['hero']['meeting_schedule'],
            'meetingLocation' => $sectionPayload['hero']['meeting_location'],
            'faqItems' => $faqItems,
            'images' => [
                'hero_primary' => $sectionPayload['hero']['primary_image'],
                'hero_secondary' => $sectionPayload['hero']['secondary_image'],
                'seo_og' => $content?->ogImage?->resolvedUrl()
                    ?: ($this->imageResolver->resolveGlobalFallback('og')['url'] ?? asset('images/sm/the-mayor.jpg')),
            ],
            'sections' => $sectionPayload,
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

    /**
     * @return Collection<int, HomeSection>
     */
    private function sectionsForLanguage(?int $languageId): Collection
    {
        if (! $languageId) {
            return collect();
        }

        return HomeSection::query()
            ->where('is_active', true)
            ->where('language_id', $languageId)
            ->with([
                'image',
                'items' => fn ($query) => $query->where('is_active', true),
            ])
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * @param Collection<int, HomeSection> $sections
     * @return array<string, mixed>
     */
    private function buildSectionPayload(Collection $sections, ?HomePageContent $content): array
    {
        $byKey = $sections->keyBy('section_key');
        $hero = $byKey->get(HomeSectionKey::Hero->value);
        $heroMeta = (array) ($hero?->meta ?? []);
        $secondaryImage = isset($heroMeta['secondary_image_id'])
            ? $this->resolveImageById((int) $heroMeta['secondary_image_id'])
            : null;

        $impact = $byKey->get(HomeSectionKey::ImpactStats->value);
        $about = $byKey->get(HomeSectionKey::About->value);
        $pathway = $byKey->get(HomeSectionKey::Pathway->value);
        $parallax = $byKey->get(HomeSectionKey::Parallax->value);
        $serve = $byKey->get(HomeSectionKey::Serve->value);
        $serveSupport = $byKey->get(HomeSectionKey::ServeSupport->value);
        $preGiveCta = $byKey->get(HomeSectionKey::PreGiveCta->value);
        $give = $byKey->get(HomeSectionKey::Give->value);
        $giveMeta = (array) ($give?->meta ?? []);
        $visit = $byKey->get(HomeSectionKey::Visit->value);
        $visitMeta = (array) ($visit?->meta ?? []);
        $finalCta = $byKey->get(HomeSectionKey::FinalCta->value);

        return [
            'hero' => [
                'eyebrow' => $hero?->eyebrow ?: 'Bread of Grace Ministries',
                'heading' => $hero?->heading ?: 'Help restore lives through God\'s Word and practical support.',
                'subheading' => $hero?->subheading ?: 'Serving since 2010',
                'note' => $hero?->note ?: 'Church Without Walls • Thu + Sun 11am',
                'intro' => $hero?->body
                    ?: ($content?->hero_intro ?: 'As a homeless ministry in Sacramento, California, we feed the hungry, help the needy, and walk with people through Christ-centered mentorship, practical support, and pathways to stable housing.'),
                'location' => $heroMeta['location'] ?? 'Sacramento, CA',
                'meeting_schedule' => $heroMeta['meeting_schedule'] ?? ($content?->meeting_schedule ?: 'Thursday + Sunday • 11:00am'),
                'meeting_location' => $heroMeta['meeting_location'] ?? ($content?->meeting_location ?: 'Township 9 Park • Sacramento'),
                'scripture_text' => $heroMeta['scripture_text'] ?? '“And whoever gives one of these little ones only a cup of cold water... shall by no means lose his reward.”',
                'scripture_reference' => $heroMeta['scripture_reference'] ?? 'Matthew 10:42',
                'primary_image' => $hero?->image?->resolvedUrl() ?: ($content?->heroImage?->resolvedUrl() ?: asset('images/sm/the-mayor.jpg')),
                'secondary_image' => $secondaryImage ?: ($content?->featuredImage?->resolvedUrl() ?: asset('images/sm/lisa-hug.jpg')),
                'ctas' => [
                    [
                        'label' => $hero?->cta_primary_label ?: 'Give today',
                        'url' => $hero?->cta_primary_url ?: '#give-form',
                    ],
                    [
                        'label' => $hero?->cta_secondary_label ?: 'Volunteer with us',
                        'url' => $hero?->cta_secondary_url ?: '#serve',
                    ],
                    [
                        'label' => $hero?->cta_tertiary_label ?: 'Visit Thursday/Sunday →',
                        'url' => $hero?->cta_tertiary_url ?: '#visit',
                    ],
                ],
                'quick_choices' => $this->mapItems($hero, 'quick_choice', [
                    ['label' => 'Give', 'title' => 'Fuel outreach', 'description' => 'Meals, supplies, mentorship', 'url' => '#give-form'],
                    ['label' => 'Serve', 'title' => 'Join the team', 'description' => 'Hands + hearts welcome', 'url' => route('volunteer.apply', ['need' => 'general'])],
                    ['label' => 'Learn', 'title' => 'How it works', 'description' => 'Our 3-phase pathway', 'url' => '#about'],
                ]),
            ],
            'impact_stats' => [
                'items' => $this->mapItems($impact, 'stat', [
                    ['title' => 'Weekly', 'description' => 'Street outreach + church service'],
                    ['title' => 'Meals', 'description' => 'Food + supplies distributed regularly'],
                    ['title' => 'Mentorship', 'description' => 'Discipleship + life coaching'],
                ]),
            ],
            'about' => [
                'heading' => $about?->heading ?: 'A simple path to restoration.',
                'body' => $about?->body ?: 'We believe transformation is spiritual and practical. So we combine consistent discipleship with tangible steps that rebuild stability and dignity.',
                'note' => $about?->note ?: 'What you\'ll see in our outreach',
                'bullets' => $this->mapItems($about, 'bullet', [
                    ['title' => 'Bible teaching + prayer'],
                    ['title' => 'Hot meals + supplies'],
                    ['title' => 'Mentorship + coaching'],
                    ['title' => 'Job/housing direction'],
                ]),
            ],
            'pathway' => [
                'heading' => $pathway?->heading ?: '3 phases to rehabilitation',
                'subheading' => $pathway?->subheading ?: 'Built for real life: spiritual foundation + next practical step.',
                'items' => $this->mapItems($pathway, 'phase', [
                    ['label' => '01', 'title' => 'Rehabilitation + community housing', 'description' => 'Counseling, mentorship, discipleship, and stabilization.'],
                    ['label' => '02', 'title' => 'Education + job training', 'description' => 'Skills, readiness, and ongoing Christ-centered coaching.'],
                    ['label' => '03', 'title' => 'Permanent housing + career placement', 'description' => 'Long-term stability with continued community support.'],
                ]),
            ],
            'parallax' => [
                'eyebrow' => $parallax?->eyebrow ?: 'Mentoring • Coaching • Discipleship',
                'heading' => $parallax?->heading ?: 'Nobody rebuilds alone.',
                'body' => $parallax?->body ?: 'We walk alongside people with consistent spiritual guidance, practical life coaching, and Christ-centered community - helping restore identity, purpose, and momentum.',
                'background_image' => $parallax?->image?->resolvedUrl() ?: asset('images/sm/bible-scriptures.jpg'),
            ],
            'serve' => [
                'eyebrow' => $serve?->eyebrow ?: 'Serve • Outreach Team',
                'heading' => $serve?->heading ?: 'Serve with Bread of Grace.',
                'body' => $serve?->body ?: 'Some people give. Some people show up. Some do both. There\'s a place for you - prayer, food service, conversations, discipleship, logistics.',
                'location' => Arr::get($serve?->meta, 'location', 'Sacramento'),
                'background_image' => $serve?->image?->resolvedUrl() ?: asset('images/sm/bike-path-road.jpg'),
                'ctas' => [
                    ['label' => $serve?->cta_primary_label ?: 'Sign up to Volunteer', 'url' => $serve?->cta_primary_url ?: route('volunteer.apply', ['need' => 'general'])],
                    ['label' => $serve?->cta_secondary_label ?: 'Support the work', 'url' => $serve?->cta_secondary_url ?: '#give-form'],
                    ['label' => $serve?->cta_tertiary_label ?: 'Come this Thursday/Sunday', 'url' => $serve?->cta_tertiary_url ?: '#visit'],
                ],
            ],
            'serve_support' => [
                'heading' => $serveSupport?->heading ?: 'A few “easy yes” ways to help',
                'tip' => $serveSupport?->body ?: 'People remember warmth and consistency more than speeches. Just showing up matters.',
                'items' => $this->mapItems($serveSupport, 'easy_yes', [
                    ['title' => 'Bring water / hygiene kits / socks'],
                    ['title' => 'Help serve meals + cleanup'],
                    ['title' => 'Prayer + conversation + encouragement'],
                    ['title' => 'Mentorship / discipleship follow-up'],
                ]),
            ],
            'pre_give_cta' => [
                'eyebrow' => $preGiveCta?->eyebrow ?: 'Next step',
                'heading' => $preGiveCta?->heading ?: 'Ready to make a real difference today?',
                'body' => $preGiveCta?->body ?: 'Your gift helps meals, supplies, and consistent discipleship happen every week.',
                'label' => $preGiveCta?->cta_primary_label ?: 'Jump to donation form →',
                'url' => $preGiveCta?->cta_primary_url ?: '#give-form',
            ],
            'give' => [
                'eyebrow' => $give?->eyebrow ?: 'Give • One-time or Monthly',
                'heading' => $give?->heading ?: 'Make outreach possible this week.',
                'body' => $give?->body ?: 'Your gift helps feed the hungry and help those in need through meals, survival supplies, discipleship, and practical next steps toward stability. Monthly giving helps us plan with confidence.',
                'heart_label' => $give?->note ?: 'Our heart',
                'scripture' => $give?->subheading ?: '“For I was hungry and you gave Me food; I was thirsty and you gave Me drink; I was a stranger and you took Me in...”',
                'scripture_reference' => $giveMeta['scripture_reference'] ?? 'Matthew 25:35',
                'give_now_label' => $give?->cta_primary_label ?: 'Give now',
                'background_image' => $give?->image?->resolvedUrl() ?: asset('images/sm/group-joseph-peace.jpg'),
                'impact_cards' => $this->mapItems($give, 'impact_card', [
                    ['title' => 'Meals', 'description' => 'Hot food served with dignity'],
                    ['title' => 'Supplies', 'description' => 'Hygiene, clothing, essentials'],
                    ['title' => 'Mentorship', 'description' => 'Discipleship + coaching'],
                ]),
            ],
            'visit' => [
                'heading' => $visit?->heading ?: 'Visit us',
                'meeting_schedule' => $visitMeta['meeting_schedule'] ?? ($content?->meeting_schedule ?: 'Thursday + Sunday • 11:00am'),
                'meeting_location' => $visitMeta['meeting_location'] ?? ($content?->meeting_location ?: 'Township 9 Park • Sacramento'),
                'directions_label' => $visit?->cta_primary_label ?: 'Get Directions',
                'directions_url' => $visit?->cta_primary_url ?: 'https://goo.gl/maps/uD7kDihreYD3nXjcA',
                'map_embed_url' => $visitMeta['map_embed_url'] ?? 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3118.0671267815537!2d-121.49328698440546!3d38.60132517194608!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x809ad710c885a0bd%3A0x82f6d5b85e40b630!2sBread%20of%20Grace%20Ministries!5e0!3m2!1sen!2sus!4v1668623874529!5m2!1sen!2sus',
            ],
            'final_cta' => [
                'eyebrow' => $finalCta?->eyebrow ?: 'Next step',
                'heading' => $finalCta?->heading ?: 'Ready to make a real difference today?',
                'body' => $finalCta?->body ?: 'Your gift helps meals, supplies, and consistent discipleship happen every week.',
                'label' => $finalCta?->cta_primary_label ?: 'Jump to donation form →',
                'url' => $finalCta?->cta_primary_url ?: '#give-form',
            ],
        ];
    }

    private function resolveImageById(int $id): ?string
    {
        return Image::query()->find($id)?->resolvedUrl();
    }

    /**
     * @param array<int, array<string, string>> $fallbacks
     * @return array<int, array<string, string|null>>
     */
    private function mapItems(?HomeSection $section, string $itemKey, array $fallbacks): array
    {
        if (! $section) {
            return $fallbacks;
        }

        $records = $section->items
            ->where('item_key', $itemKey)
            ->values()
            ->map(fn ($item) => [
                'label' => $item->label,
                'title' => $item->title,
                'description' => $item->description,
                'value' => $item->value,
                'url' => $item->url,
            ])
            ->all();

        return count($records) > 0 ? $records : $fallbacks;
    }
}
