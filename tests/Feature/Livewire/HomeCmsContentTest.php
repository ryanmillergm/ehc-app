<?php

namespace Tests\Feature\Livewire;

use App\Models\FaqItem;
use App\Models\HomePageContent;
use App\Models\Image;
use App\Models\HomeSection;
use App\Models\HomeSectionItem;
use App\Models\Language;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HomeCmsContentTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function home_renders_faq_items_from_database(): void
    {
        $language = Language::factory()->english()->create();
        session(['language_id' => $language->id]);

        FaqItem::factory()->create([
            'context' => 'home',
            'language_id' => $language->id,
            'question' => 'How does the FAQ load?',
            'answer' => 'It comes from the database.',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('How does the FAQ load?')
            ->assertSee('It comes from the database.');
    }

    #[Test]
    public function home_uses_db_seo_title_and_og_image_when_present(): void
    {
        $language = Language::factory()->english()->create();
        session(['language_id' => $language->id]);

        $ogImage = Image::factory()->create([
            'public_url' => 'https://cdn.example.org/seo-home-og.jpg',
        ]);

        HomePageContent::factory()->create([
            'language_id' => $language->id,
            'seo_title' => 'Custom Home SEO Title',
            'seo_description' => 'Custom Home SEO Description',
            'og_image_id' => $ogImage->id,
        ]);

        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('<title>Custom Home SEO Title</title>', false)
            ->assertSee('<meta property="og:image" content="https://cdn.example.org/seo-home-og.jpg">', false)
            ->assertSee('<meta name="description" content="Custom Home SEO Description">', false);
    }

    #[Test]
    public function home_renders_section_content_from_home_sections_tables(): void
    {
        $language = Language::factory()->english()->create();
        session(['language_id' => $language->id]);

        $hero = HomeSection::factory()->create([
            'language_id' => $language->id,
            'section_key' => 'hero',
            'heading' => 'Custom Hero Heading from DB',
            'body' => 'Custom hero intro text from home_sections.',
            'cta_primary_label' => 'Give Right Now',
            'cta_primary_url' => '#give-form',
        ]);

        HomeSectionItem::factory()->create([
            'home_section_id' => $hero->id,
            'item_key' => 'quick_choice',
            'label' => 'Learn',
            'title' => 'Custom Learn Card',
            'description' => 'Custom description from DB item',
            'sort_order' => 1,
        ]);

        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('Custom Hero Heading from DB')
            ->assertSee('Custom hero intro text from home_sections.')
            ->assertSee('Give Right Now')
            ->assertSee('Custom Learn Card')
            ->assertSee('Custom description from DB item');
    }

    #[Test]
    public function home_renders_final_cta_component_with_database_content(): void
    {
        $language = Language::factory()->english()->create();
        session(['language_id' => $language->id]);

        HomeSection::factory()->create([
            'language_id' => $language->id,
            'section_key' => 'final_cta',
            'eyebrow' => 'Custom CTA Eyebrow',
            'heading' => 'Custom CTA Heading',
            'body' => 'Custom CTA body from database.',
            'cta_primary_label' => 'Custom CTA Button',
            'cta_primary_url' => '#custom-give-anchor',
        ]);

        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('Custom CTA Eyebrow')
            ->assertSee('Custom CTA Heading')
            ->assertSee('Custom CTA body from database.')
            ->assertSee('Custom CTA Button')
            ->assertSee('href="#custom-give-anchor"', false);
    }

    #[Test]
    public function home_renders_pre_give_cta_block_with_database_content(): void
    {
        $language = Language::factory()->english()->create();
        session(['language_id' => $language->id]);

        HomeSection::factory()->create([
            'language_id' => $language->id,
            'section_key' => 'pre_give_cta',
            'eyebrow' => 'Custom Pre Give Eyebrow',
            'heading' => 'Custom Pre Give Heading',
            'body' => 'Custom pre-give bridge text.',
            'cta_primary_label' => 'Custom Jump Label',
            'cta_primary_url' => '#custom-pre-give-anchor',
        ]);

        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('Custom Pre Give Eyebrow')
            ->assertSee('Custom Pre Give Heading')
            ->assertSee('Custom pre-give bridge text.')
            ->assertSee('Custom Jump Label')
            ->assertSee('href="#custom-pre-give-anchor"', false);
    }

    #[Test]
    public function pre_give_cta_override_does_not_override_final_cta_component(): void
    {
        $language = Language::factory()->english()->create();
        session(['language_id' => $language->id]);

        HomeSection::factory()->create([
            'language_id' => $language->id,
            'section_key' => 'pre_give_cta',
            'eyebrow' => 'Only Pre Give Eyebrow',
            'heading' => 'Only Pre Give Heading',
            'body' => 'Only pre give text.',
            'cta_primary_label' => 'Only Pre Give Button',
            'cta_primary_url' => '#only-pre-give',
        ]);

        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('Only Pre Give Heading')
            ->assertSee('Only Pre Give Button')
            ->assertSee('href="#only-pre-give"', false)
            ->assertSee('Ready to make a real difference today?')
            ->assertSee('Jump to donation form →');
    }

    #[Test]
    public function final_cta_override_does_not_override_pre_give_cta_block(): void
    {
        $language = Language::factory()->english()->create();
        session(['language_id' => $language->id]);

        HomeSection::factory()->create([
            'language_id' => $language->id,
            'section_key' => 'final_cta',
            'eyebrow' => 'Only Final CTA Eyebrow',
            'heading' => 'Only Final CTA Heading',
            'body' => 'Only final CTA body.',
            'cta_primary_label' => 'Only Final CTA Button',
            'cta_primary_url' => '#only-final-cta',
        ]);

        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('Only Final CTA Heading')
            ->assertSee('Only Final CTA Button')
            ->assertSee('href="#only-final-cta"', false)
            ->assertSee('Ready to make a real difference today?')
            ->assertSee('Jump to donation form →');
    }
}
