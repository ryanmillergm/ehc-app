<?php

namespace Tests\Feature\Livewire\Pages;

use App\Livewire\Pages\IndexPage;
use App\Models\Language;
use App\Models\Page;
use App\Models\PageTranslation;
use Illuminate\Foundation\Testing\Concerns\WithoutExceptionHandlingHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Livewire\Livewire;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class IndexPageTest extends TestCase
{
    use WithFaker, RefreshDatabase;

    /** @test */
    #[Test]
    public function test_renders_successfully()
    {
        Livewire::test(IndexPage::class)
            ->assertStatus(200);
    }


    /** @test */
    #[Test]
    public function displays_all_pages_for_default_language_english()
    {
        $this->seed();

        $response = $this->get('/pages');
        $response->assertOk();
    
        // Livewire::test(IndexPage::class)
        //     ->assertViewHas('pageTranslations', function ($translations) {
        //         return count($translations) == 1;
        //     });

        $this->get('/pages')
            ->assertSeeLivewire(IndexPage::class);

        Livewire::test(IndexPage::class)
            ->assertSee('Blog Test Title Example')
            ->assertDontSee('Blog Test Content Example');

        // Need to test that it only displays pages with the language set in the session
        
    }
}
