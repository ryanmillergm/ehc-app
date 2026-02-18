<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\AdminDocumentation;
use App\Filament\Pages\HomeSectionsDocumentation;
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

    public function test_admin_documentation_contains_images_quick_jump_label(): void
    {
        $this->get(AdminDocumentation::getUrl())
            ->assertOk()
            ->assertSee('Images');
    }

    public function test_admin_documentation_links_to_home_sections_documentation(): void
    {
        $this->get(AdminDocumentation::getUrl())
            ->assertOk()
            ->assertSee('Open Home Sections Documentation')
            ->assertSee(HomeSectionsDocumentation::getUrl());
    }
}
