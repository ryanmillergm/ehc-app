<?php

namespace Tests\Feature\Livewire\Pages;

use App\Livewire\Pages\ShowPage;
use App\Models\Image;
use App\Models\Imageable;
use App\Models\ImageGroup;
use App\Models\ImageGroupItem;
use App\Models\ImageGroupable;
use App\Models\Language;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\Video;
use App\Models\Videoable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ShowPageTest extends TestCase
{
    use RefreshDatabase;

    protected Language $en;
    protected Language $es;
    protected Language $fr;

    protected function setUp(): void
    {
        parent::setUp();

        $this->en = Language::create([
            'title' => 'English', 'iso_code' => 'en', 'locale' => 'en', 'right_to_left' => false,
        ]);
        $this->es = Language::create([
            'title' => 'Spanish', 'iso_code' => 'es', 'locale' => 'es', 'right_to_left' => false,
        ]);
        $this->fr = Language::create([
            'title' => 'French',  'iso_code' => 'fr', 'locale' => 'fr', 'right_to_left' => false,
        ]);
    }

    #[Test]
    public function renders_successfully()
    {
        $page = Page::factory()->create(['is_active' => true]);

        $translationEn = PageTranslation::factory()->create([
            'page_id'     => $page->id,
            'language_id' => $this->en->id,
            'slug'        => 'about-us-en',
            'title'       => 'About Us',
            'description' => 'English description',
            'content'     => '<p>English content</p>',
            'is_active'   => true,
        ]);

        Livewire::test(ShowPage::class, ['slug' => $translationEn->slug])
            ->assertStatus(200);
    }

    #[Test]
    public function test_component_exists_on_the_page()
    {
        session(['language_id' => $this->en->id]);

        $page = Page::factory()->create(['is_active' => true]);

        $translationEn = PageTranslation::factory()->create([
            'page_id'     => $page->id,
            'language_id' => $this->en->id,
            'slug'        => 'events-en',
            'is_active'   => true,
        ]);

        $this->get('/pages/' . $translationEn->slug)
            ->assertOk()
            ->assertSeeLivewire(ShowPage::class);
    }

    #[Test]
    public function test_displays_page_translation_when_slug_matches_current_language()
    {
        session(['language_id' => $this->en->id]);

        $page = Page::factory()->create(['is_active' => true]);

        $translationEn = PageTranslation::factory()->create([
            'page_id'      => $page->id,
            'language_id'  => $this->en->id,
            'slug'         => 'donate-en',
            'title'        => 'Donate',
            'description'  => 'Your gift helps us reach more people.',
            'content'      => '<h2>Give Today</h2><p>Thanks!</p>',
            'is_active'    => true,
        ]);

        Livewire::test(ShowPage::class, ['slug' => $translationEn->slug])
            ->assertSee('Donate')
            ->assertSee('Your gift helps us reach more people.')
            ->assertSee('Give Today');
    }

    #[Test]
    public function test_displays_current_language_translation_when_slug_is_other_language_but_translation_exists()
    {
        session(['language_id' => $this->es->id, 'locale' => 'es']);

        $page = Page::factory()->create(['is_active' => true]);

        $translationEn = PageTranslation::factory()->create([
            'page_id'     => $page->id,
            'language_id' => $this->en->id,
            'slug'        => 'about-us-en',
            'title'       => 'About Us',
            'description' => 'English description',
            'content'     => '<p>English content</p>',
            'is_active'   => true,
        ]);

        $translationEs = PageTranslation::factory()->create([
            'page_id'     => $page->id,
            'language_id' => $this->es->id,
            'slug'        => 'sobre-nosotros-es',
            'title'       => 'Sobre Nosotros',
            'description' => 'Descripción en español',
            'content'     => '<p>Contenido en español</p>',
            'is_active'   => true,
        ]);

        Livewire::test(ShowPage::class, ['slug' => $translationEn->slug])
            ->assertSee($translationEs->title)
            ->assertSee($translationEs->description)
            ->assertSee('Contenido en español');
    }

    #[Test]
    public function test_displays_default_translation_when_current_language_missing()
    {
        session(['language_id' => $this->es->id, 'locale' => 'es']);

        $page = Page::factory()->create(['is_active' => true]);

        $translationEn = PageTranslation::factory()->create([
            'page_id'     => $page->id,
            'language_id' => $this->en->id,
            'slug'        => 'privacy-en',
            'title'       => 'Privacy Policy',
            'description' => 'English only',
            'content'     => '<p>English privacy</p>',
            'is_active'   => true,
        ]);

        Livewire::test(ShowPage::class, ['slug' => $translationEn->slug])
            ->assertSee('Privacy Policy')
            ->assertSee('English only')
            ->assertSee('English privacy');
    }

    #[Test]
    public function test_displays_slug_translation_when_neither_current_nor_default_exist()
    {
        session(['language_id' => $this->es->id, 'locale' => 'es']);

        $page = Page::factory()->create(['is_active' => true]);

        $translationFr = PageTranslation::factory()->create([
            'page_id'     => $page->id,
            'language_id' => $this->fr->id,
            'slug'        => 'a-propos-fr',
            'title'       => 'À Propos',
            'description' => 'Description FR',
            'content'     => '<p>Contenu FR</p>',
            'is_active'   => true,
        ]);

        Livewire::test(ShowPage::class, ['slug' => $translationFr->slug])
            ->assertSee('À Propos')
            ->assertSee('Description FR')
            ->assertSee('Contenu FR');
    }

    #[Test]
    public function test_redirects_to_page_index_if_no_page_exists()
    {
        $response = $this->get('/pages/does-not-exist-123');

        $response->assertRedirect('/pages');
    }

    #[Test]
    public function test_switches_translation_in_place_when_language_switched_event_received()
    {
        session(['language_id' => $this->en->id, 'locale' => 'en']);

        $page = Page::factory()->create(['is_active' => true]);

        $enTx = PageTranslation::factory()->create([
            'page_id'     => $page->id,
            'language_id' => $this->en->id,
            'slug'        => 'events-en',
            'title'       => 'Events',
            'description' => 'English events',
            'content'     => '<p>English content</p>',
            'is_active'   => true,
        ]);

        $esTx = PageTranslation::factory()->create([
            'page_id'     => $page->id,
            'language_id' => $this->es->id,
            'slug'        => 'eventos-es',
            'title'       => 'Eventos',
            'description' => 'Eventos en español',
            'content'     => '<p>Contenido en español</p>',
            'is_active'   => true,
        ]);

        $lw = Livewire::test(ShowPage::class, ['slug' => $enTx->slug])
            ->assertSee('Events')
            ->assertDontSee('Eventos');

        // Simulate Navbar switching session language
        session(['language_id' => $this->es->id, 'locale' => 'es']);

        $lw->dispatch('language-switched', code: 'es')
            ->assertSee('Eventos')
            ->assertSee('Eventos en español')
            ->assertDontSee('English events');
    }

    #[Test]
    public function test_switch_falls_back_to_default_translation_when_target_language_missing()
    {
        session(['language_id' => $this->en->id, 'locale' => 'en']);

        $page = Page::factory()->create(['is_active' => true]);

        $enTx = PageTranslation::factory()->create([
            'page_id'     => $page->id,
            'language_id' => $this->en->id,
            'slug'        => 'privacy-en',
            'title'       => 'Privacy',
            'description' => 'English only',
            'content'     => '<p>English privacy</p>',
            'is_active'   => true,
        ]);

        $lw = Livewire::test(ShowPage::class, ['slug' => $enTx->slug])
            ->assertSee('Privacy');

        // Switch to ES but ES translation doesn't exist
        session(['language_id' => $this->es->id, 'locale' => 'es']);

        $lw->dispatch('language-switched', code: 'es')
            ->assertSee('Privacy')
            ->assertSee('English only');
    }

    #[Test]
    public function unpublished_translation_is_not_publicly_rendered(): void
    {
        $page = Page::factory()->create(['is_active' => true]);

        $translation = PageTranslation::factory()->create([
            'page_id' => $page->id,
            'language_id' => $this->en->id,
            'slug' => 'future-page-en',
            'title' => 'Future Page',
            'is_active' => true,
            'published_at' => now()->addDay(),
        ]);

        $this->get('/pages/' . $translation->slug)
            ->assertRedirect('/pages');
    }

    #[Test]
    public function unknown_template_falls_back_to_standard_template(): void
    {
        $page = Page::factory()->create(['is_active' => true]);

        $translation = PageTranslation::factory()->create([
            'page_id' => $page->id,
            'language_id' => $this->en->id,
            'slug' => 'template-fallback-en',
            'title' => 'Template Fallback',
            'description' => 'Description',
            'content' => '<p>Body</p>',
            'template' => 'not-a-real-template',
            'hero_mode' => 'none',
            'is_active' => true,
        ]);

        $this->get('/pages/' . $translation->slug)
            ->assertOk()
            ->assertSee('Template Fallback')
            ->assertSee('data-page-template="standard"', false);
    }

    #[Test]
    public function hero_video_mode_renders_video_when_assignment_exists(): void
    {
        $page = Page::factory()->create(['is_active' => true]);

        $translation = PageTranslation::factory()->create([
            'page_id' => $page->id,
            'language_id' => $this->en->id,
            'slug' => 'hero-video-en',
            'title' => 'Hero Video',
            'hero_mode' => 'video',
            'is_active' => true,
        ]);

        $video = Video::factory()->embed()->create([
            'embed_url' => 'https://www.youtube.com/embed/abc123xyz',
        ]);

        Videoable::factory()->create([
            'video_id' => $video->id,
            'videoable_type' => PageTranslation::class,
            'videoable_id' => $translation->id,
            'role' => 'hero_video',
            'is_active' => true,
        ]);

        $this->get('/pages/' . $translation->slug)
            ->assertOk()
            ->assertSee('<iframe', false)
            ->assertSee('youtube.com/embed/abc123xyz')
            ->assertSee('data-hero-background-video="true"', false)
            ->assertSee('playlist=abc123xyz', false);
    }

    #[Test]
    public function hero_video_mode_falls_back_when_no_video_assignment_exists(): void
    {
        $page = Page::factory()->create(['is_active' => true]);

        $translation = PageTranslation::factory()->create([
            'page_id' => $page->id,
            'language_id' => $this->en->id,
            'slug' => 'hero-video-missing-en',
            'title' => 'Hero Video Missing',
            'hero_mode' => 'video',
            'is_active' => true,
        ]);

        $this->get('/pages/' . $translation->slug)
            ->assertOk()
            ->assertDontSee('<iframe', false)
            ->assertDontSee('<video', false);
    }

    #[Test]
    public function hero_video_mode_renders_featured_video_when_hero_video_is_missing(): void
    {
        $page = Page::factory()->create(['is_active' => true]);

        $translation = PageTranslation::factory()->create([
            'page_id' => $page->id,
            'language_id' => $this->en->id,
            'slug' => 'hero-video-featured-fallback-en',
            'title' => 'Hero Video Featured Fallback',
            'hero_mode' => 'video',
            'is_active' => true,
        ]);

        $video = Video::factory()->embed()->create([
            'embed_url' => 'https://www.youtube.com/embed/fallback123',
        ]);

        Videoable::factory()->create([
            'video_id' => $video->id,
            'videoable_type' => PageTranslation::class,
            'videoable_id' => $translation->id,
            'role' => 'featured_video',
            'is_active' => true,
        ]);

        $this->get('/pages/' . $translation->slug)
            ->assertOk()
            ->assertSee('<iframe', false)
            ->assertSee('youtube.com/embed/fallback123')
            ->assertSee('data-hero-background-video="true"', false)
            ->assertSee('playlist=fallback123', false);
    }

    #[Test]
    public function hero_video_mode_does_not_render_when_only_featured_video_is_inactive(): void
    {
        $page = Page::factory()->create(['is_active' => true]);

        $translation = PageTranslation::factory()->create([
            'page_id' => $page->id,
            'language_id' => $this->en->id,
            'slug' => 'hero-video-featured-inactive-en',
            'title' => 'Hero Video Featured Inactive',
            'hero_mode' => 'video',
            'is_active' => true,
        ]);

        $video = Video::factory()->embed()->create([
            'embed_url' => 'https://www.youtube.com/embed/inactiveFeatured123',
        ]);

        Videoable::factory()->create([
            'video_id' => $video->id,
            'videoable_type' => PageTranslation::class,
            'videoable_id' => $translation->id,
            'role' => 'featured_video',
            'is_active' => false,
        ]);

        $this->get('/pages/' . $translation->slug)
            ->assertOk()
            ->assertDontSee('<iframe', false)
            ->assertDontSee('<video', false);
    }

    #[Test]
    public function seo_fields_override_defaults_in_page_meta(): void
    {
        $page = Page::factory()->create(['is_active' => true]);

        $translation = PageTranslation::factory()->create([
            'page_id' => $page->id,
            'language_id' => $this->en->id,
            'slug' => 'seo-override-en',
            'title' => 'Base Title',
            'description' => 'Base Description',
            'seo_title' => 'SEO Override Title',
            'seo_description' => 'SEO Override Description',
            'seo_og_image' => 'https://cdn.example.org/seo-override.jpg',
            'is_active' => true,
        ]);

        $this->get('/pages/' . $translation->slug)
            ->assertOk()
            ->assertSee('<title>SEO Override Title</title>', false)
            ->assertSee('<meta name="description" content="SEO Override Description">', false)
            ->assertSee('<meta property="og:image" content="https://cdn.example.org/seo-override.jpg">', false);
    }

    #[Test]
    public function page_meta_falls_back_to_title_and_description_when_seo_fields_missing(): void
    {
        $page = Page::factory()->create(['is_active' => true]);

        $translation = PageTranslation::factory()->create([
            'page_id' => $page->id,
            'language_id' => $this->en->id,
            'slug' => 'seo-fallback-en',
            'title' => 'Fallback Title',
            'description' => 'Fallback Description',
            'seo_title' => null,
            'seo_description' => null,
            'seo_og_image' => null,
            'is_active' => true,
        ]);

        $this->get('/pages/' . $translation->slug)
            ->assertOk()
            ->assertSee('<title>Fallback Title</title>', false)
            ->assertSee('<meta name="description" content="Fallback Description">', false);
    }

    #[Test]
    public function dedicated_keyword_page_renders_expected_canonical_and_indexable_meta(): void
    {
        $page = Page::factory()->create([
            'title' => 'Homeless Ministry Sacramento',
            'is_active' => true,
        ]);

        $translation = PageTranslation::factory()->create([
            'page_id' => $page->id,
            'language_id' => $this->en->id,
            'slug' => 'homeless-ministry-sacramento',
            'title' => 'Homeless Ministry in Sacramento',
            'description' => 'Serving people experiencing homelessness in Sacramento.',
            'seo_title' => 'Homeless Ministry in Sacramento, CA | Bread of Grace Ministries',
            'seo_description' => 'Homeless ministry in Sacramento providing meals, outreach, discipleship, and practical support.',
            'is_active' => true,
            'published_at' => now()->subMinute(),
        ]);

        $this->get('/pages/' . $translation->slug)
            ->assertOk()
            ->assertSee('<title>Homeless Ministry in Sacramento, CA | Bread of Grace Ministries</title>', false)
            ->assertSee('<meta name="description" content="Homeless ministry in Sacramento providing meals, outreach, discipleship, and practical support.">', false)
            ->assertSee('<meta name="robots" content="index,follow">', false)
            ->assertSee('<link rel="canonical" href="' . url('/pages/homeless-ministry-sacramento') . '">', false);
    }

    #[Test]
    public function hero_slider_mode_falls_back_to_header_image_when_slider_has_no_items(): void
    {
        $page = Page::factory()->create(['is_active' => true]);

        $translation = PageTranslation::factory()->create([
            'page_id' => $page->id,
            'language_id' => $this->en->id,
            'slug' => 'slider-fallback-image-en',
            'title' => 'Slider Fallback Image',
            'template' => 'standard',
            'hero_mode' => 'slider',
            'is_active' => true,
        ]);

        $header = Image::factory()->create();

        Imageable::factory()->create([
            'image_id' => $header->id,
            'imageable_type' => PageTranslation::class,
            'imageable_id' => $translation->id,
            'role' => 'header',
            'is_active' => true,
        ]);

        Livewire::test(ShowPage::class, ['slug' => $translation->slug])
            ->assertSee($header->public_url);
    }

    #[Test]
    public function hero_slider_mode_stays_hidden_when_no_slider_items_and_no_header_image(): void
    {
        $page = Page::factory()->create(['is_active' => true]);

        $translation = PageTranslation::factory()->create([
            'page_id' => $page->id,
            'language_id' => $this->en->id,
            'slug' => 'slider-fallback-none-en',
            'title' => 'Slider Fallback None',
            'template' => 'standard',
            'hero_mode' => 'slider',
            'is_active' => true,
        ]);

        $group = ImageGroup::factory()->create();

        ImageGroupable::factory()->create([
            'image_group_id' => $group->id,
            'image_groupable_type' => PageTranslation::class,
            'image_groupable_id' => $translation->id,
            'role' => 'hero_slider',
            'is_active' => true,
        ]);

        $inactiveSlideImage = Image::factory()->create();
        ImageGroupItem::factory()->create([
            'image_group_id' => $group->id,
            'image_id' => $inactiveSlideImage->id,
            'is_active' => false,
        ]);

        Livewire::test(ShowPage::class, ['slug' => $translation->slug])
            ->assertDontSee($inactiveSlideImage->public_url)
            ->assertDontSee('<iframe')
            ->assertDontSee('<video');
    }

    #[Test]
    public function hero_image_mode_stays_hidden_when_header_image_is_missing(): void
    {
        $page = Page::factory()->create(['is_active' => true]);

        $translation = PageTranslation::factory()->create([
            'page_id' => $page->id,
            'language_id' => $this->en->id,
            'slug' => 'image-fallback-none-en',
            'title' => 'Image Fallback None',
            'template' => 'standard',
            'hero_mode' => 'image',
            'is_active' => true,
        ]);

        Livewire::test(ShowPage::class, ['slug' => $translation->slug])
            ->assertSee('Image Fallback None')
            ->assertDontSee('<iframe')
            ->assertDontSee('<video');
    }

    #[Test]
    public function blocks_render_mode_outputs_block_content(): void
    {
        $page = Page::factory()->create(['is_active' => true]);

        $translation = PageTranslation::factory()->create([
            'page_id' => $page->id,
            'language_id' => $this->en->id,
            'slug' => 'blocks-render-en',
            'title' => 'Blocks Render',
            'render_mode' => 'blocks',
            'content_blocks' => [
                [
                    'type' => 'hero',
                    'data' => [
                        'heading' => 'Blocks Heading',
                        'subheading' => 'Blocks Subheading',
                    ],
                ],
            ],
            'is_active' => true,
        ]);

        $this->get('/pages/' . $translation->slug)
            ->assertOk()
            ->assertSee('data-page-block="hero"', false)
            ->assertSee('data-block-hero-mode="none"', false)
            ->assertSee('from-slate-950 via-slate-900 to-rose-950', false)
            ->assertSee('Blocks Heading')
            ->assertSee('Blocks Subheading');
    }

    #[Test]
    public function blocks_hero_image_mode_renders_page_header_image(): void
    {
        $page = Page::factory()->create(['is_active' => true]);

        $translation = PageTranslation::factory()->create([
            'page_id' => $page->id,
            'language_id' => $this->en->id,
            'slug' => 'blocks-hero-image-en',
            'title' => 'Blocks Hero Image',
            'render_mode' => 'blocks',
            'content_blocks' => [
                [
                    'type' => 'hero',
                    'data' => [
                        'heading' => 'Image Block Hero',
                        'hero_mode' => 'image',
                    ],
                ],
            ],
            'is_active' => true,
        ]);

        $image = Image::factory()->create([
            'public_url' => 'https://cdn.example.org/block-hero-header.jpg',
        ]);

        Imageable::factory()->create([
            'image_id' => $image->id,
            'imageable_type' => PageTranslation::class,
            'imageable_id' => $translation->id,
            'role' => 'header',
            'is_active' => true,
        ]);

        $this->get('/pages/' . $translation->slug)
            ->assertOk()
            ->assertSee('data-block-hero-mode="image"', false)
            ->assertSee('https://cdn.example.org/block-hero-header.jpg')
            ->assertSee('<img', false);
    }

    #[Test]
    public function blocks_hero_video_mode_renders_page_hero_video(): void
    {
        $page = Page::factory()->create(['is_active' => true]);

        $translation = PageTranslation::factory()->create([
            'page_id' => $page->id,
            'language_id' => $this->en->id,
            'slug' => 'blocks-hero-video-en',
            'title' => 'Blocks Hero Video',
            'render_mode' => 'blocks',
            'content_blocks' => [
                [
                    'type' => 'hero',
                    'data' => [
                        'heading' => 'Video Block Hero',
                        'hero_mode' => 'video',
                        'hero_style' => 'full_bleed',
                        'hero_height' => '100',
                    ],
                ],
            ],
            'is_active' => true,
        ]);

        $video = Video::factory()->embed()->create([
            'embed_url' => 'https://www.youtube.com/embed/blockHeroVideo123',
        ]);

        Videoable::factory()->create([
            'video_id' => $video->id,
            'videoable_type' => PageTranslation::class,
            'videoable_id' => $translation->id,
            'role' => 'hero_video',
            'is_active' => true,
        ]);

        $this->get('/pages/' . $translation->slug)
            ->assertOk()
            ->assertSee('data-block-hero-mode="video"', false)
            ->assertSee('data-hero-background-video="true"', false)
            ->assertSee('min-h-[calc(100svh-4rem)]', false)
            ->assertSee('md:min-h-[calc(100vh-4rem)]', false)
            ->assertSee('youtube.com/embed/blockHeroVideo123')
            ->assertSee('playlist=blockHeroVideo123', false);
    }

    #[Test]
    public function blocks_hero_slider_mode_renders_first_active_slider_image(): void
    {
        $page = Page::factory()->create(['is_active' => true]);

        $translation = PageTranslation::factory()->create([
            'page_id' => $page->id,
            'language_id' => $this->en->id,
            'slug' => 'blocks-hero-slider-en',
            'title' => 'Blocks Hero Slider',
            'render_mode' => 'blocks',
            'content_blocks' => [
                [
                    'type' => 'hero',
                    'data' => [
                        'heading' => 'Slider Block Hero',
                        'hero_mode' => 'slider',
                    ],
                ],
            ],
            'is_active' => true,
        ]);

        $group = ImageGroup::factory()->create();

        ImageGroupable::factory()->create([
            'image_group_id' => $group->id,
            'image_groupable_type' => PageTranslation::class,
            'image_groupable_id' => $translation->id,
            'role' => 'hero_slider',
            'is_active' => true,
        ]);

        $slide = Image::factory()->create([
            'public_url' => 'https://cdn.example.org/block-slider-slide.jpg',
        ]);

        ImageGroupItem::factory()->create([
            'image_group_id' => $group->id,
            'image_id' => $slide->id,
            'is_active' => true,
        ]);

        $this->get('/pages/' . $translation->slug)
            ->assertOk()
            ->assertSee('data-block-hero-mode="slider"', false)
            ->assertSee('https://cdn.example.org/block-slider-slide.jpg');
    }

    #[Test]
    public function blocks_hero_slider_mode_falls_back_to_header_image_when_slider_is_empty(): void
    {
        $page = Page::factory()->create(['is_active' => true]);

        $translation = PageTranslation::factory()->create([
            'page_id' => $page->id,
            'language_id' => $this->en->id,
            'slug' => 'blocks-hero-slider-fallback-en',
            'title' => 'Blocks Hero Slider Fallback',
            'render_mode' => 'blocks',
            'content_blocks' => [
                [
                    'type' => 'hero',
                    'data' => [
                        'heading' => 'Slider Fallback Block Hero',
                        'hero_mode' => 'slider',
                    ],
                ],
            ],
            'is_active' => true,
        ]);

        $group = ImageGroup::factory()->create();

        ImageGroupable::factory()->create([
            'image_group_id' => $group->id,
            'image_groupable_type' => PageTranslation::class,
            'image_groupable_id' => $translation->id,
            'role' => 'hero_slider',
            'is_active' => true,
        ]);

        $header = Image::factory()->create([
            'public_url' => 'https://cdn.example.org/block-slider-header-fallback.jpg',
        ]);

        Imageable::factory()->create([
            'image_id' => $header->id,
            'imageable_type' => PageTranslation::class,
            'imageable_id' => $translation->id,
            'role' => 'header',
            'is_active' => true,
        ]);

        $this->get('/pages/' . $translation->slug)
            ->assertOk()
            ->assertSee('data-block-hero-mode="slider"', false)
            ->assertSee('https://cdn.example.org/block-slider-header-fallback.jpg');
    }

    #[Test]
    public function blocks_renderer_constrains_standard_and_wide_blocks(): void
    {
        $page = Page::factory()->create(['is_active' => true]);

        $translation = PageTranslation::factory()->create([
            'page_id' => $page->id,
            'language_id' => $this->en->id,
            'slug' => 'blocks-layout-shell-en',
            'title' => 'Blocks Layout Shell',
            'render_mode' => 'blocks',
            'content_blocks' => [
                [
                    'type' => 'video',
                    'data' => [
                        'url' => 'https://www.youtube.com/embed/layoutShell123',
                        'caption' => 'Constrained video caption',
                    ],
                ],
                [
                    'type' => 'stats',
                    'data' => [
                        'items' => [
                            [
                                'label' => 'Meals',
                                'value' => '500+',
                            ],
                        ],
                    ],
                ],
            ],
            'is_active' => true,
        ]);

        $this->get('/pages/' . $translation->slug)
            ->assertOk()
            ->assertSee('mx-auto max-w-5xl px-5 py-5', false)
            ->assertSee('mx-auto max-w-screen-2xl px-5 py-5', false)
            ->assertSee('Constrained video caption')
            ->assertSee('500+');
    }

    #[Test]
    public function blocks_render_mode_renders_structured_gallery_sources(): void
    {
        $page = Page::factory()->create(['is_active' => true]);
        $image = Image::factory()->create([
            'public_url' => 'https://cdn.example.org/gallery-existing.jpg',
            'alt_text' => 'Existing image model alt',
            'caption' => 'Existing image model caption',
        ]);

        $translation = PageTranslation::factory()->create([
            'page_id' => $page->id,
            'language_id' => $this->en->id,
            'slug' => 'blocks-gallery-structured-en',
            'title' => 'Blocks Gallery Structured',
            'render_mode' => 'blocks',
            'content_blocks' => [
                [
                    'type' => 'gallery',
                    'data' => [
                        'items' => [
                            [
                                'source_type' => 'existing',
                                'image_id' => $image->id,
                                'alt' => 'Existing gallery alt',
                                'caption' => 'Existing structured caption',
                            ],
                            [
                                'source_type' => 'url',
                                'src' => 'https://cdn.example.org/gallery-url.jpg',
                                'alt' => 'Remote gallery alt',
                                'caption' => 'Remote structured caption',
                            ],
                        ],
                    ],
                ],
            ],
            'is_active' => true,
        ]);

        $this->get('/pages/' . $translation->slug)
            ->assertOk()
            ->assertSee('data-page-block="gallery"', false)
            ->assertSee('https://cdn.example.org/gallery-existing.jpg')
            ->assertSee('alt="Existing gallery alt"', false)
            ->assertSee('Existing structured caption')
            ->assertSee('https://cdn.example.org/gallery-url.jpg')
            ->assertSee('alt="Remote gallery alt"', false)
            ->assertSee('Remote structured caption');
    }

    #[Test]
    public function blocks_render_mode_renders_structured_repeater_blocks(): void
    {
        $page = Page::factory()->create(['is_active' => true]);

        $translation = PageTranslation::factory()->create([
            'page_id' => $page->id,
            'language_id' => $this->en->id,
            'slug' => 'blocks-repeaters-structured-en',
            'title' => 'Blocks Repeaters Structured',
            'render_mode' => 'blocks',
            'content_blocks' => [
                [
                    'type' => 'stats',
                    'data' => [
                        'items' => [
                            ['label' => 'Structured Meals', 'value' => '1,200+'],
                        ],
                    ],
                ],
                [
                    'type' => 'icon_list',
                    'data' => [
                        'items' => [
                            ['text' => 'Structured icon item'],
                        ],
                    ],
                ],
                [
                    'type' => 'feature_grid',
                    'data' => [
                        'items' => [
                            ['title' => 'Structured Feature', 'body' => 'Feature body copy'],
                        ],
                    ],
                ],
                [
                    'type' => 'testimonials',
                    'data' => [
                        'items' => [
                            ['quote' => 'Structured testimony quote', 'name' => 'Structured Person'],
                        ],
                    ],
                ],
                [
                    'type' => 'timeline',
                    'data' => [
                        'items' => [
                            ['title' => 'Structured Timeline Step', 'body' => 'Timeline body copy'],
                        ],
                    ],
                ],
                [
                    'type' => 'pricing',
                    'data' => [
                        'items' => [
                            [
                                'name' => 'Structured Plan',
                                'price' => '$25',
                                'features' => [
                                    ['text' => 'Structured pricing feature'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'is_active' => true,
        ]);

        $this->get('/pages/' . $translation->slug)
            ->assertOk()
            ->assertSee('Structured Meals')
            ->assertSee('1,200+')
            ->assertSee('Structured icon item')
            ->assertSee('Structured Feature')
            ->assertSee('Feature body copy')
            ->assertSee('Structured testimony quote')
            ->assertSee('Structured Person')
            ->assertSee('Structured Timeline Step')
            ->assertSee('Timeline body copy')
            ->assertSee('Structured Plan')
            ->assertSee('$25')
            ->assertSee('Structured pricing feature');
    }

    #[Test]
    public function blocks_render_mode_does_not_render_legacy_json_block_fields(): void
    {
        $page = Page::factory()->create(['is_active' => true]);

        $translation = PageTranslation::factory()->create([
            'page_id' => $page->id,
            'language_id' => $this->en->id,
            'slug' => 'blocks-legacy-json-en',
            'title' => 'Blocks Legacy JSON',
            'render_mode' => 'blocks',
            'content_blocks' => [
                [
                    'type' => 'gallery',
                    'data' => [
                        'images_json' => '[{"src":"https://cdn.example.org/legacy-gallery.jpg","caption":"Legacy gallery caption"}]',
                    ],
                ],
                [
                    'type' => 'icon_list',
                    'data' => [
                        'items_json' => '["Legacy icon item"]',
                    ],
                ],
                [
                    'type' => 'stats',
                    'data' => [
                        'items_json' => '[{"label":"Legacy Stat","value":"999"}]',
                    ],
                ],
            ],
            'is_active' => true,
        ]);

        $this->get('/pages/' . $translation->slug)
            ->assertOk()
            ->assertDontSee('legacy-gallery.jpg')
            ->assertDontSee('Legacy gallery caption')
            ->assertDontSee('Legacy icon item')
            ->assertDontSee('Legacy Stat')
            ->assertDontSee('999');
    }

    #[Test]
    public function custom_render_mode_outputs_custom_html_without_scripts(): void
    {
        $page = Page::factory()->create(['is_active' => true]);

        $translation = PageTranslation::factory()->create([
            'page_id' => $page->id,
            'language_id' => $this->en->id,
            'slug' => 'custom-render-en',
            'title' => 'Custom Render',
            'render_mode' => 'custom',
            'custom_html_is_trusted' => true,
            'custom_html' => '<div onclick="alert(1)">Custom Body<script>alert(2)</script></div>',
            'is_active' => true,
        ])->refresh();

        $this->assertStringNotContainsString('<script', (string) $translation->custom_html);
        $this->assertStringNotContainsString('onclick=', (string) $translation->custom_html);

        $this->get('/pages/' . $translation->slug)
            ->assertOk()
            ->assertSee('Custom Body')
            ->assertDontSee('alert(2)')
            ->assertDontSee('onclick=');
    }

    #[Test]
    public function unknown_render_mode_falls_back_to_template_renderer(): void
    {
        $page = Page::factory()->create(['is_active' => true]);

        $translation = PageTranslation::factory()->create([
            'page_id' => $page->id,
            'language_id' => $this->en->id,
            'slug' => 'render-mode-fallback-en',
            'title' => 'Render Mode Fallback',
            'description' => 'Description',
            'content' => '<p>Body</p>',
            'render_mode' => 'not-a-real-mode',
            'template' => 'standard',
            'is_active' => true,
        ]);

        $this->get('/pages/' . $translation->slug)
            ->assertOk()
            ->assertSee('Render Mode Fallback')
            ->assertSee('data-page-template="standard"', false);
    }

    #[Test]
    public function blocks_render_mode_renders_cta_block_output(): void
    {
        $page = Page::factory()->create(['is_active' => true]);

        $translation = PageTranslation::factory()->create([
            'page_id' => $page->id,
            'language_id' => $this->en->id,
            'slug' => 'blocks-cta-render-en',
            'title' => 'Blocks CTA Render',
            'render_mode' => 'blocks',
            'content_blocks' => [
                [
                    'type' => 'cta',
                    'data' => [
                        'title' => 'Support This Mission',
                        'body' => 'Your gift helps directly.',
                        'button_text' => 'Give Now',
                        'button_url' => '/give',
                    ],
                ],
            ],
            'is_active' => true,
        ]);

        $this->get('/pages/' . $translation->slug)
            ->assertOk()
            ->assertSee('Support This Mission')
            ->assertSee('Your gift helps directly.')
            ->assertSee('Give Now')
            ->assertDontSee('Call To Action');
    }

    #[Test]
    public function immersive_template_renders_when_selected(): void
    {
        $page = Page::factory()->create(['is_active' => true]);

        $translation = PageTranslation::factory()->create([
            'page_id' => $page->id,
            'language_id' => $this->en->id,
            'slug' => 'immersive-template-en',
            'title' => 'Immersive Template Page',
            'description' => 'Immersive description',
            'content' => '<p>Immersive body</p>',
            'template' => 'immersive',
            'hero_mode' => 'none',
            'is_active' => true,
        ]);

        $this->get('/pages/' . $translation->slug)
            ->assertOk()
            ->assertSee('data-page-template="immersive"', false)
            ->assertSee('Immersive Template Page');
    }

    #[Test]
    public function campaign_contained_100vh_hero_accounts_for_navbar_and_gutters(): void
    {
        $page = Page::factory()->create(['is_active' => true]);

        $translation = PageTranslation::factory()->create([
            'page_id' => $page->id,
            'language_id' => $this->en->id,
            'slug' => 'campaign-contained-hero-video-en',
            'title' => 'Campaign Contained Hero Video',
            'template' => 'campaign',
            'hero_mode' => 'video',
            'hero_style' => 'contained',
            'hero_height' => '100',
            'is_active' => true,
        ]);

        $video = Video::factory()->embed()->create([
            'embed_url' => 'https://www.youtube.com/embed/campaignContained123',
        ]);

        Videoable::factory()->create([
            'video_id' => $video->id,
            'videoable_type' => PageTranslation::class,
            'videoable_id' => $translation->id,
            'role' => 'hero_video',
            'is_active' => true,
        ]);

        $this->get('/pages/' . $translation->slug)
            ->assertOk()
            ->assertSee('data-page-template="campaign"', false)
            ->assertSee('py-8', false)
            ->assertSee('sm:py-10', false)
            ->assertSee('min-h-[calc(100svh-8rem)]', false)
            ->assertSee('sm:min-h-[calc(100svh-9rem)]', false)
            ->assertSee('md:min-h-[calc(100vh-9rem)]', false)
            ->assertSee('youtube.com/embed/campaignContained123');
    }

    #[Test]
    public function campaign_full_bleed_100vh_hero_accounts_for_navbar(): void
    {
        $page = Page::factory()->create(['is_active' => true]);

        $translation = PageTranslation::factory()->create([
            'page_id' => $page->id,
            'language_id' => $this->en->id,
            'slug' => 'full-bleed-hero-video-en',
            'title' => 'Full Bleed Hero Video',
            'template' => 'campaign',
            'hero_mode' => 'video',
            'hero_style' => 'full_bleed',
            'hero_height' => '100',
            'is_active' => true,
        ]);

        $video = Video::factory()->embed()->create([
            'embed_url' => 'https://www.youtube.com/embed/fullBleedTest123',
        ]);

        Videoable::factory()->create([
            'video_id' => $video->id,
            'videoable_type' => PageTranslation::class,
            'videoable_id' => $translation->id,
            'role' => 'hero_video',
            'is_active' => true,
        ]);

        $this->get('/pages/' . $translation->slug)
            ->assertOk()
            ->assertSee('w-screen')
            ->assertSee('min-h-[calc(100svh-4rem)]', false)
            ->assertSee('md:min-h-[calc(100vh-4rem)]', false)
            ->assertSee('youtube.com/embed/fullBleedTest123');
    }
}
