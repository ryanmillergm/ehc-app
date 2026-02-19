<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\SeoDocumentation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoDocumentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_with_permission_can_access_seo_documentation(): void
    {
        $this->seed('PermissionSeeder');
        $this->signInWithPermissions(null, ['admin.panel']);

        $this->get(SeoDocumentation::getUrl())
            ->assertOk()
            ->assertSee('SEO Documentation')
            ->assertSee('Route SEO CMS Guide')
            ->assertSee('Noindex Policy');
    }

    public function test_user_without_permission_cannot_access_seo_documentation(): void
    {
        $this->seed('PermissionSeeder');
        $this->signIn();

        $this->get(SeoDocumentation::getUrl())
            ->assertForbidden();
    }
}
