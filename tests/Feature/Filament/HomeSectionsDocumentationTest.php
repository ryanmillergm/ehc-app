<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\HomeSectionsDocumentation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeSectionsDocumentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_with_permission_can_access_home_sections_documentation(): void
    {
        $this->seed('PermissionSeeder');
        $this->signInWithPermissions(null, ['admin.panel']);

        $this->get(HomeSectionsDocumentation::getUrl())
            ->assertOk()
            ->assertSee('Home Sections Documentation')
            ->assertSee('Section Keys');
    }

    public function test_user_without_permission_cannot_access_home_sections_documentation(): void
    {
        $this->seed('PermissionSeeder');
        $this->signIn();

        $this->get(HomeSectionsDocumentation::getUrl())
            ->assertForbidden();
    }
}

