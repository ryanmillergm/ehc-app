<?php

namespace Tests\Feature\Database;

use App\Models\Page;
use App\Models\PageTranslation;
use Database\Seeders\HomelessMinistrySacramentoPageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HomelessMinistrySacramentoPageSeederTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_the_dedicated_homeless_ministry_page_with_seo_and_layout_data(): void
    {
        $this->seed([
            HomelessMinistrySacramentoPageSeeder::class,
        ]);

        $page = Page::query()
            ->where('title', 'Homeless Ministry Sacramento')
            ->firstOrFail();

        $translation = PageTranslation::query()
            ->where('page_id', $page->id)
            ->where('slug', 'homeless-ministry-sacramento')
            ->firstOrFail();

        $this->assertSame('Homeless Ministry in Sacramento', $translation->title);
        $this->assertSame('campaign', $translation->template);
        $this->assertSame('Give to Support Outreach', $translation->hero_cta_text);
        $this->assertSame('/give', $translation->hero_cta_url);
        $this->assertSame('Homeless Ministry in Sacramento, CA | Bread of Grace Ministries', $translation->seo_title);
        $this->assertTrue($translation->is_active);
        $this->assertNotNull($translation->published_at);

        $layout = $translation->layout_data ?? [];
        $this->assertSame('Bread of Grace Ministries - Sacramento', $layout['eyebrow'] ?? null);
        $this->assertCount(3, $layout['trust_badges'] ?? []);
        $this->assertCount(3, $layout['impact_stats'] ?? []);
    }

    #[Test]
    public function it_is_idempotent_when_run_multiple_times(): void
    {
        $this->seed([
            HomelessMinistrySacramentoPageSeeder::class,
        ]);

        $this->seed([
            HomelessMinistrySacramentoPageSeeder::class,
        ]);

        $this->assertSame(1, Page::query()->where('title', 'Homeless Ministry Sacramento')->count());
        $this->assertSame(1, PageTranslation::query()->where('slug', 'homeless-ministry-sacramento')->count());
    }
}
