<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\PageAuthoringHelp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageAuthoringHelpTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_with_permission_can_access_page_authoring_help(): void
    {
        $this->seed('PermissionSeeder');
        $this->signInWithPermissions(null, ['admin.panel']);

        $this->get(PageAuthoringHelp::getUrl())
            ->assertOk()
            ->assertSee('Page Authoring Help')
            ->assertSee('Hero Video')
            ->assertSee('Video Relationship')
            ->assertSee('Template')
            ->assertSee('Block Builder')
            ->assertSee('Custom HTML')
            ->assertSee('YouTube/Vimeo hero embeds are best-effort backgrounds')
            ->assertSee('Uploaded MP4/WebM videos are preferred');
    }

    public function test_user_without_permission_cannot_access_page_authoring_help(): void
    {
        $this->seed('PermissionSeeder');
        $this->signIn();

        $this->get(PageAuthoringHelp::getUrl())
            ->assertForbidden();
    }
}
