<?php

namespace Tests\Feature\Filament;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ChildResourceTest extends TestCase
{
    use WithFaker, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed('PermissionSeeder');

        // $this->signIn();
        $this->signInWithPermissions(null, ['children.read', 'children.create', 'children.update', 'children.delete', 'admin.panel']);
    }

    /**
     * Test an authenticated user with permissions can visit the children resource page in the filament admin panel.
     */
    public function test_an_authenticated_user_with_permissions_can_render_the_user_resource_page(): void
    {
        $this->get(ChildResource::getUrl('index'))->assertSuccessful();
    }

    /**
     * Test an authenticated user with permissions can visit the children resource table builder list page in the filament admin panel.
     */
    // public function test_an_authenticated_user_with_permissions_can_render_the_children_resource_table_page(): void
    // {
    //     livewire::test(ListTeams::class)->assertSuccessful();
    // }
}
