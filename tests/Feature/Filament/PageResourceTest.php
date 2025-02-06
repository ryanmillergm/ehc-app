<?php

namespace Tests\Feature\Filament;

use App\Models\User;
use App\Filament\Resources\PageResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class PageResourceTest extends TestCase
{
    use WithFaker, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed('PermissionSeeder');
        $this->seed('PageSeeder');
    }

    /**
     * An authenticated user with Super Admin role can visit the Page resource in the filament admin panel.
     */
    public function test_an_authenticated_user_with_super_admin_role_can_visit_admin_page_and_view_page_resource(): void
    {
        $user = User::factory()->create();
        $this->signInAsSuperAdmin($user);

        $response = $this->get('/admin');

        $response->assertStatus(200);

        $this->get(PageResource::getUrl('index'))->assertSuccessful();
    }


    /**
     * Test an authenticated user with permissions can visit the Page resource page in the filament admin panel.
     */
    public function test_an_authenticated_user_with_permissions_can_render_the_page_resource_page(): void
    {
        $this->signInWithPermissions(null, ['pages.read', 'admin.panel']);

        $this->get(PageResource::getUrl('index'))->assertSuccessful();
    }
}
