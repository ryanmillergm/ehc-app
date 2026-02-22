<?php

namespace Tests\Unit;

use App\Models\Language;
use App\Models\Page;
use App\Models\PageTranslation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageTranslationsTest extends TestCase
{
    use RefreshDatabase;
    
    
    public function test_a_page_translation_belongs_to_a_page()
    {
        $translation = PageTranslation::factory()->create();

        $this->assertInstanceOf(Page::class, $translation->page);
    }

    public function test_a_page_translation_belongs_to_a_language()
    {
        $translation = PageTranslation::factory()->create();

        $this->assertInstanceOf(Language::class, $translation->language);
    }

    public function test_primary_rich_text_fields_are_sanitized_on_save(): void
    {
        $translation = PageTranslation::factory()->create([
            'title' => '<script>alert(1)</script><span class="text-rose-700">Title</span>',
            'description' => '<a href="javascript:alert(1)" class="bad">Bad</a><a href="https://example.org" class="underline">Good</a>',
            'content' => '<p class="lead">Body</p><script>evil()</script>',
            'hero_title' => '<span class="font-bold">Hero</span>',
            'hero_subtitle' => '<script>alert(2)</script><em class="italic">Sub</em>',
            'hero_cta_text' => '<span class="tracking-wide">Act</span>',
        ])->refresh();

        $this->assertStringNotContainsString('<script', (string) $translation->title);
        $this->assertStringContainsString('class="text-rose-700"', (string) $translation->title);

        $this->assertStringNotContainsString('javascript:', (string) $translation->description);
        $this->assertStringContainsString('https://example.org', (string) $translation->description);
        $this->assertStringContainsString('class="underline"', (string) $translation->description);

        $this->assertStringNotContainsString('<script', (string) $translation->content);
        $this->assertStringContainsString('<p class="lead">Body</p>', (string) $translation->content);
        $this->assertStringContainsString('class="font-bold"', (string) $translation->hero_title);
        $this->assertStringContainsString('class="italic"', (string) $translation->hero_subtitle);
        $this->assertStringContainsString('class="tracking-wide"', (string) $translation->hero_cta_text);
    }

    public function test_layout_data_is_sanitized_recursively_on_save(): void
    {
        $translation = PageTranslation::factory()->create([
            'layout_data' => [
                'eyebrow' => '<script>x()</script><span class="uppercase">Eyebrow</span>',
                'cta_secondary_text' => '<span class="underline">Secondary</span>',
                'faq_teaser_title' => '<strong class="font-bold">FAQ</strong>',
                'faq_teaser_body' => '<a href="javascript:alert(1)" class="bad">Bad</a><a href="https://example.org" class="underline">Good</a>',
                'trust_badges' => [
                    '<span class="badge">Trusted</span>',
                    '<script>x()</script>Safe',
                ],
                'quick_facts' => [
                    '<em class="italic">Fact A</em>',
                ],
                'impact_stats' => [
                    [
                        'label' => '<script>x()</script><span class="label">Meals</span>',
                        'value' => '<span class="value">500+</span>',
                    ],
                ],
            ],
        ])->refresh();

        $layout = $translation->layout_data;

        $this->assertStringNotContainsString('<script', (string) ($layout['eyebrow'] ?? ''));
        $this->assertStringContainsString('class="uppercase"', (string) ($layout['eyebrow'] ?? ''));
        $this->assertStringContainsString('class="underline"', (string) ($layout['cta_secondary_text'] ?? ''));
        $this->assertStringNotContainsString('javascript:', (string) ($layout['faq_teaser_body'] ?? ''));
        $this->assertStringContainsString('https://example.org', (string) ($layout['faq_teaser_body'] ?? ''));
        $this->assertStringContainsString('class="badge"', (string) (($layout['trust_badges'][0] ?? '')));
        $this->assertStringContainsString('Safe', (string) (($layout['trust_badges'][1] ?? '')));
        $this->assertStringContainsString('class="italic"', (string) (($layout['quick_facts'][0] ?? '')));
        $this->assertStringContainsString('class="label"', (string) (($layout['impact_stats'][0]['label'] ?? '')));
        $this->assertStringContainsString('class="value"', (string) (($layout['impact_stats'][0]['value'] ?? '')));
    }
}
