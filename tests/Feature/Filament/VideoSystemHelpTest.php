<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\VideoSystemHelp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VideoSystemHelpTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_with_permission_can_access_video_system_help(): void
    {
        $this->seed('PermissionSeeder');
        $this->signInWithPermissions(null, ['admin.panel']);

        $this->get(VideoSystemHelp::getUrl())
            ->assertOk()
            ->assertSee('Video System Help')
            ->assertSee('Hero Video')
            ->assertSee('Featured Video');
    }

    public function test_user_without_permission_cannot_access_video_system_help(): void
    {
        $this->seed('PermissionSeeder');
        $this->signIn();

        $this->get(VideoSystemHelp::getUrl())
            ->assertForbidden();
    }
}
