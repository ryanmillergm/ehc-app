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
    use WithFaker, RefreshDatabase;
    
    /** @test */
    #[Test]
    public function renders_successfully()
    {
        Livewire::test(ShowPage::class)
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
        $this->seed();

        // dd(PageTranslation::all());
        $page = Page::with('pageTranslations')->find(4);
        $translation_1 = $page->pageTranslations->first();

        $response = $this->get('/pages/' . $translation_1->slug);
        $response->assertOk();

        $this->get('/pages/' . $translation_1->slug)
            ->assertSeeLivewire(ShowPage::class);
    
        Livewire::test(ShowPage::class)
            ->assertSee('Blog Test Title Example')
            ->assertSee('Blog Test Description Example')
            ->assertSee('Blog Test Content Example');
    }
}
