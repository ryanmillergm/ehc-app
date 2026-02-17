<?php

namespace Tests\Feature\Livewire;

use App\Models\FaqItem;
use App\Models\HomePageContent;
use App\Models\Image;
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
}
