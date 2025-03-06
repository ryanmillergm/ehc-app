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
    public function test_component_exists_on_the_page()
    {
        $this->withoutExceptionHandling();

        $page = Page::factory()->create();
        $language = Language::factory()->create();

        $translation = PageTranslation::factory()->create(['language_id' => $language->id, 'page_id' => $page->id]);

        $response = $this->get('/' . $translation->slug);
        $response->assertOk();

        $this->get('/' . $translation->slug)
            ->assertSeeLivewire(IndexPage::class);
    }
}
