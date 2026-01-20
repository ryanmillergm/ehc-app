<?php

namespace Tests\Feature\Livewire\Pages;

use App\Livewire\Pages\ShowPage;
use App\Models\Language;
use App\Models\Page;
use App\Models\PageTranslation;
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
}
