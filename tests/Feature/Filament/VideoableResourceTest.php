<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\VideoSystemHelp;
use App\Filament\Resources\Videoables\Pages\CreateVideoable;
use App\Filament\Resources\Videoables\Pages\EditVideoable;
use App\Filament\Resources\Videoables\Pages\ListVideoables;
use App\Models\HomePageContent;
use App\Models\PageTranslation;
use App\Models\User;
use App\Models\Video;
use App\Models\Videoable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class VideoableResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed('PermissionSeeder');
        $this->signInWithPermissions(null, ['admin.panel']);
    }

    public function test_list_page_shows_help_link_to_video_system_help(): void
    {
        Livewire::actingAs(User::first())
            ->test(ListVideoables::class)
            ->assertSee('Help')
            ->assertSee('href="' . VideoSystemHelp::getUrl() . '"', false);
    }

    public function test_edit_page_shows_help_link_to_video_system_help(): void
    {
        $video = Video::factory()->create();
        $target = HomePageContent::factory()->create();
        $videoable = Videoable::factory()->create([
            'video_id' => $video->id,
            'videoable_type' => HomePageContent::class,
            'videoable_id' => $target->id,
        ]);

        Livewire::actingAs(User::first())
            ->test(EditVideoable::class, ['record' => $videoable->getRouteKey()])
            ->assertSee('Help')
            ->assertSee('href="' . VideoSystemHelp::getUrl() . '"', false);
    }

    public function test_videoable_id_rejects_negative_values(): void
    {
        $video = Video::factory()->create();

        Livewire::actingAs(User::first())
            ->test(CreateVideoable::class)
            ->fillForm([
                'video_id' => $video->id,
                'videoable_type' => PageTranslation::class,
                'videoable_id' => -77,
                'role' => 'hero_video',
                'sort_order' => 0,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['videoable_id']);
    }

    public function test_videoable_relationship_can_be_created_with_valid_target(): void
    {
        $video = Video::factory()->create();
        $target = HomePageContent::factory()->create();

        Livewire::actingAs(User::first())
            ->test(CreateVideoable::class)
            ->fillForm([
                'video_id' => $video->id,
                'videoable_type' => HomePageContent::class,
                'videoable_id' => $target->id,
                'role' => 'featured_video',
                'sort_order' => 0,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(Videoable::class, [
            'video_id' => $video->id,
            'videoable_type' => HomePageContent::class,
            'videoable_id' => $target->id,
            'role' => 'featured_video',
            'is_active' => true,
        ]);
    }

    public function test_videoable_id_fails_when_target_does_not_exist_for_selected_type(): void
    {
        $video = Video::factory()->create();
        $invalidForPageTranslationId = HomePageContent::factory()->count(2)->create()->last()->id;

        Livewire::actingAs(User::first())
            ->test(CreateVideoable::class)
            ->fillForm([
                'video_id' => $video->id,
                'videoable_type' => PageTranslation::class,
                'videoable_id' => $invalidForPageTranslationId,
                'role' => 'hero_video',
                'sort_order' => 0,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['videoable_id']);
    }

    public function test_videoable_type_rejects_unknown_model_class(): void
    {
        $video = Video::factory()->create();
        $target = HomePageContent::factory()->create();

        Livewire::actingAs(User::first())
            ->test(CreateVideoable::class)
            ->fillForm([
                'video_id' => $video->id,
                'videoable_type' => 'App\\Models\\UnknownModel',
                'videoable_id' => $target->id,
                'role' => 'hero_video',
                'sort_order' => 0,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['videoable_type']);
    }

    public function test_videoables_table_shows_human_readable_related_type_labels(): void
    {
        $video = Video::factory()->create();
        $target = PageTranslation::factory()->create();

        Videoable::factory()->create([
            'video_id' => $video->id,
            'videoable_type' => PageTranslation::class,
            'videoable_id' => $target->id,
            'role' => 'hero_video',
            'sort_order' => 0,
            'is_active' => true,
        ]);

        Livewire::actingAs(User::first())
            ->test(ListVideoables::class)
            ->assertSee('Page Translation')
            ->assertDontSee(PageTranslation::class);
    }

    public function test_create_videoable_form_shows_expected_video_role_labels(): void
    {
        Livewire::actingAs(User::first())
            ->test(CreateVideoable::class)
            ->assertSee('Hero Video')
            ->assertSee('Featured Video')
            ->assertSee('Inline Video');
    }
}
