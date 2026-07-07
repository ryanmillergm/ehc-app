<?php

namespace Tests\Feature\Livewire\Pages;

use App\Livewire\Pages\IndexPage;
use App\Models\Language;
use App\Models\Page;
use App\Models\PageTranslation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IndexPageTest extends TestCase
{
    use WithFaker, RefreshDatabase;

    /**
     * If your base TestCase auto-seeds, this tells Laravel’s RefreshDatabase
     * not to seed for this class (where supported).
     */
    protected bool $seed = false;

    protected Language $en;
    protected Language $es;
    protected Language $fr;

    protected function setUp(): void
    {
        parent::setUp();

        /**
         * HARD RESET for this suite:
         * If your base TestCase or traits seeded Pages/Translations,
         * wipe them so each test controls the universe.
         */
        PageTranslation::query()->delete();
        Page::query()->delete();
        Language::query()->delete();

        // Recreate languages exactly like LanguageSeeder
        $this->en = Language::create([
            'title' => 'English',
            'iso_code' => 'en',
            'locale' => 'en',
            'right_to_left' => false,
        ]);

        $this->es = Language::create([
            'title' => 'Spanish',
            'iso_code' => 'es',
            'locale' => 'es',
            'right_to_left' => false,
        ]);

        $this->fr = Language::create([
            'title' => 'French',
            'iso_code' => 'fr',
            'locale' => 'fr',
            'right_to_left' => false,
        ]);

        session(['language_id' => $this->en->id, 'locale' => 'en']);
        app()->setLocale('en');
    }

    #[Test]
    public function test_renders_successfully()
    {
        Livewire::test(IndexPage::class)
            ->assertStatus(200);
    }

    #[Test]
    public function displays_pages_in_current_language_when_available()
    {
        session(['language_id' => $this->es->id, 'locale' => 'es']);
        app()->setLocale('es');

        $page = Page::factory()->create(['is_active' => true]);

        PageTranslation::factory()->create([
            'page_id'     => $page->id,
            'language_id' => $this->en->id,
            'title'       => 'About Us',
            'slug'        => 'about-us-en',
            'description' => 'English description',
            'content'     => '<p>English content</p>',
            'is_active'   => true,
        ]);

        PageTranslation::factory()->create([
            'page_id'     => $page->id,
            'language_id' => $this->es->id,
            'title'       => 'Sobre Nosotros',
            'slug'        => 'sobre-nosotros-es',
            'description' => 'Descripción en español',
            'content'     => '<p>Contenido en español</p>',
            'is_active'   => true,
        ]);

        Livewire::test(IndexPage::class)
            ->assertSee('Sobre Nosotros')
            ->assertSee('Descripción en español')
            ->assertDontSee('About Us')
            ->assertDontSee(__('pages.only_available_in_english'));
    }

    #[Test]
    public function falls_back_to_english_and_shows_english_only_badge_when_current_language_missing()
    {
        session(['language_id' => $this->es->id, 'locale' => 'es']);
        app()->setLocale('es');

        $page = Page::factory()->create(['is_active' => true]);

        PageTranslation::factory()->create([
            'page_id'     => $page->id,
            'language_id' => $this->en->id,
            'title'       => 'Privacy Policy',
            'slug'        => 'privacy-en',
            'description' => 'English only',
            'content'     => '<p>English privacy</p>',
            'is_active'   => true,
        ]);

        Livewire::test(IndexPage::class)
            ->assertSee('Privacy Policy')
            ->assertSee('English only')
            ->assertSee(__('pages.only_available_in_english'));
    }

    #[Test]
    public function falls_back_to_any_active_translation_when_neither_current_nor_english_exist()
    {
        session(['language_id' => $this->es->id, 'locale' => 'es']);
        app()->setLocale('es');

        $page = Page::factory()->create(['is_active' => true]);

        PageTranslation::factory()->create([
            'page_id'     => $page->id,
            'language_id' => $this->fr->id,
            'title'       => 'À Propos',
            'slug'        => 'a-propos-fr',
            'description' => 'Description FR',
            'content'     => '<p>Contenu FR</p>',
            'is_active'   => true,
        ]);

        Livewire::test(IndexPage::class)
            ->assertSee('À Propos')
            ->assertSee('Description FR')
            ->assertDontSee(__('pages.only_available_in_english'));
    }

    #[Test]
    public function excludes_inactive_pages_and_inactive_translations()
    {
        $activePage = Page::factory()->create(['is_active' => true]);
        PageTranslation::factory()->create([
            'page_id'     => $activePage->id,
            'language_id' => $this->en->id,
            'title'       => 'Donate',
            'slug'        => 'donate-en',
            'description' => 'Active EN',
            'content'     => '<p>Donate content</p>',
            'is_active'   => true,
        ]);

        $pageWithInactiveTx = Page::factory()->create(['is_active' => true]);
        PageTranslation::factory()->create([
            'page_id'     => $pageWithInactiveTx->id,
            'language_id' => $this->en->id,
            'title'       => 'Hidden Page',
            'slug'        => 'hidden-en',
            'description' => 'Inactive translation',
            'content'     => '<p>Hidden</p>',
            'is_active'   => false,
        ]);

        $inactivePage = Page::factory()->create(['is_active' => false]);
        PageTranslation::factory()->create([
            'page_id'     => $inactivePage->id,
            'language_id' => $this->en->id,
            'title'       => 'Inactive Page',
            'slug'        => 'inactive-en',
            'description' => 'Inactive page',
            'content'     => '<p>Inactive</p>',
            'is_active'   => true,
        ]);

        Livewire::test(IndexPage::class)
            ->assertSee('Donate')
            ->assertDontSee('Hidden Page')
            ->assertDontSee('Inactive Page');
    }

    #[Test]
    public function language_switched_event_re_resolves_translations_in_place()
    {
        $page = Page::factory()->create(['is_active' => true]);

        $enTx = PageTranslation::factory()->create([
            'page_id'     => $page->id,
            'language_id' => $this->en->id,
            'title'       => 'About Us',
            'slug'        => 'about-us-en',
            'description' => 'English description',
            'content'     => '<p>English</p>',
            'is_active'   => true,
        ]);

        $esTx = PageTranslation::factory()->create([
            'page_id'     => $page->id,
            'language_id' => $this->es->id,
            'title'       => 'Sobre Nosotros',
            'slug'        => 'sobre-nosotros-es',
            'description' => 'Descripción en español',
            'content'     => '<p>Español</p>',
            'is_active'   => true,
        ]);

        session(['language_id' => $this->en->id, 'locale' => 'en']);
        app()->setLocale('en');

        $lw = Livewire::test(IndexPage::class)
            ->assertSee($enTx->title)
            ->assertDontSee($esTx->title);

        session(['language_id' => $this->es->id, 'locale' => 'es']);
        app()->setLocale('es');

        $lw->dispatch('language-switched')
            ->assertSee($esTx->title)
            ->assertDontSee($enTx->title);
    }

    #[Test]
    public function pages_index_route_renders_expected_seo_meta_tags(): void
    {
        $this->get('/pages')
            ->assertOk()
            ->assertSee('<title>Community Outreach Pages | Bread of Grace Ministries</title>', false)
            ->assertSee('<meta name="description" content="Explore Bread of Grace Ministries pages on outreach, discipleship, and ways to serve and give in Sacramento.">', false)
            ->assertSee('<meta name="robots" content="index,follow">', false)
            ->assertSee('<link rel="canonical" href="' . url('/pages') . '">', false);
    }
}
