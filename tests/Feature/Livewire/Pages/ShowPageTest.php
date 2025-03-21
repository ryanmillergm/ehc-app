<?php

namespace Tests\Feature\Livewire\Pages;

use App\Livewire\Pages\ShowPage;
use App\Models\Language;
use App\Models\Page;
use App\Models\PageTranslation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ShowPageTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    public function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }
    
    /** @test */
    #[Test]
    public function renders_successfully()
    {
        $page = Page::with('pageTranslations')->where('title', 'Test translations Page')->first();
        $translation = $page->pageTranslations->first();

        Livewire::test(ShowPage::class, ['slug' => $translation->slug])
            ->assertStatus(200);
    }
    

    /** @test */
    #[Test]
    public function test_component_exists_on_the_page()
    {
        $this->withoutExceptionHandling();

        $page = Page::factory()->create();
        $language = Language::factory()->create();

        $translation = PageTranslation::factory()->create(['language_id' => $language->id, 'page_id' => $page->id]);

        $response = $this->get('/pages/' . $translation->slug);
        $response->assertOk();

        $this->get('/pages/' . $translation->slug)
            ->assertSeeLivewire(ShowPage::class);
    }


    
    /** @test */
    #[Test]
    public function test_displays_page_translation()
    {
        $english_language = Language::where('locale', 'en')->first();

        session(['language_id' => $english_language->id]);
        
        $page = Page::allActivePagesWithTranslationsByLanguage()->first();
        
        $translation_1 = $page->pageTranslations->first();

        $response = $this->get('/pages/' . $translation_1->slug);
        $response->assertOk();

        $this->get('/pages/' . $translation_1->slug)
            ->assertSeeLivewire(ShowPage::class);
    
        Livewire::test(ShowPage::class, ['slug' => $translation_1->slug])
            ->assertSee('Blog Test Title Example')
            ->assertSee('Blog Test Description Example')
            ->assertSee('Blog Test Content Example');
    }


    /** @test */
    #[Test]
    public function test_displays_default_page_translation_if_translation_for_language_does_not_exist()
    {
        $english_language = Language::where('locale', 'en')->first();
        $spanish_language = Language::where('locale', 'es')->first();
        $french_language = Language::where('locale', 'fr')->first();

        // Sets session to Spanish
        session(['language_id' => $spanish_language->id]);
        session(['locale' => $spanish_language->locale]);


        $page = Page::factory()->create([
            'title' => 'This title should be in english',
            'is_active' => true
        ]);

        $translation = PageTranslation::factory()->create(['language_id' => $english_language->id, 'page_id' => $page->id, 'is_active' => true]);
        $translation_fr = PageTranslation::factory()->create(['language_id' => $french_language->id, 'page_id' => $page->id, 'is_active' => true]);
 
        // Goes to a french translations slug
        // $response = $this->get('/pages/' . 'lsakdjfklsdajflkasdjfkojf');
        $response = $this->get('/pages/' . $translation_fr->slug);

        // Should redirect to the english translation since there is no spanish translation
        $response->assertRedirect('/pages/' . $translation->slug);
        // $response->assertSeeLivewire(ShowPage::class);
    
        // Livewire::test(ShowPage::class, ['slug' => $translation->slug])
        //     ->assertSee($translation->title)
        //     ->assertSee($translation->description);
        //     // ->assertSee($translation->content);
    }


    /** @test */
    #[Test]
    public function test_redirects_to_page_index_if_no_page_exists()
    {
        $response = $this->get('/pages/' . 'lsakdjfklsdajflkasdjfkojf');

        $response->assertRedirect('/pages/');
    }
}
