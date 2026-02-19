<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\AdminDocumentation;
use App\Filament\Pages\HomeSectionsDocumentation;
use App\Filament\Pages\SeoDocumentation;
use App\Filament\Pages\VideoSystemHelp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDocumentationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed('PermissionSeeder');
        $this->signInWithPermissions(null, ['admin.panel']);
    }

    public function test_admin_documentation_contains_media_library_quick_jump_label(): void
    {
        $this->get(AdminDocumentation::getUrl())
            ->assertOk()
            ->assertSee('Media Library');
    }

    public function test_admin_documentation_links_to_home_sections_documentation(): void
    {
        $this->get(AdminDocumentation::getUrl())
            ->assertOk()
            ->assertSee('Open Home Sections Documentation')
            ->assertSee(HomeSectionsDocumentation::getUrl());
    }

    public function test_admin_documentation_links_to_video_system_help(): void
    {
        $this->get(AdminDocumentation::getUrl())
            ->assertOk()
            ->assertSee('Open Video System Help')
            ->assertSee(VideoSystemHelp::getUrl())
            ->assertSee('Hero Video')
            ->assertSee('Featured Video');
    }

    public function test_admin_documentation_renders_video_help_link_href_exactly(): void
    {
        $this->get(AdminDocumentation::getUrl())
            ->assertOk()
            ->assertSee('href="' . VideoSystemHelp::getUrl() . '"', false);
    }

    public function test_admin_documentation_contains_header_background_video_checklist_copy(): void
    {
        $this->get(AdminDocumentation::getUrl())
            ->assertOk()
            ->assertSee('Header background video setup (Pages)')
            ->assertSee('Related Type')
            ->assertSee('Page Translation')
            ->assertSee('Hero Video')
            ->assertSee('Featured Video is fallback');
    }

    public function test_admin_documentation_links_to_seo_documentation(): void
    {
        $this->get(AdminDocumentation::getUrl())
            ->assertOk()
            ->assertSee('Open SEO Documentation')
            ->assertSee(SeoDocumentation::getUrl())
            ->assertSee('Route SEO resource');
    }
}
