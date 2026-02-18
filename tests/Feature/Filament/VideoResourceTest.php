<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Videos\Pages\CreateVideo;
use App\Filament\Resources\Videos\Pages\EditVideo;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class VideoResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed('PermissionSeeder');
        $this->signInWithPermissions(null, ['admin.panel']);
    }

    public function test_create_form_shows_video_upload_helper_copy(): void
    {
        Livewire::actingAs(User::first())
            ->test(CreateVideo::class)
            ->fillForm([
                'source_type' => 'upload',
            ])
            ->assertSee('Upload MP4 or WebM up to 500 MB')
            ->assertSee('For header/hero videos, aim for 25 MB or less')
            ->assertSee('Uploading video...')
            ->assertSee('Processing video metadata...');
    }

    public function test_livewire_temp_upload_limit_is_configured_for_500mb(): void
    {
        $rules = config('livewire.temporary_file_upload.rules');

        $this->assertIsArray($rules);
        $this->assertContains('max:512000', $rules);
    }

    public function test_metadata_fields_are_hidden_on_create(): void
    {
        Livewire::actingAs(User::first())
            ->test(CreateVideo::class)
            ->fillForm([
                'source_type' => 'upload',
            ])
            ->assertFormFieldHidden('public_url')
            ->assertFormFieldHidden('mime_type')
            ->assertFormFieldHidden('extension')
            ->assertFormFieldHidden('size_bytes')
            ->assertFormFieldHidden('duration_seconds');
    }

    public function test_metadata_fields_are_visible_and_enabled_on_edit(): void
    {
        $video = Video::factory()->create([
            'source_type' => 'upload',
            'path' => 'cms/videos/2026/02/existing.mp4',
            'public_url' => 'https://cdn.example.org/existing.mp4',
            'extension' => 'mp4',
        ]);

        Livewire::actingAs(User::first())
            ->test(EditVideo::class, ['record' => $video->getRouteKey()])
            ->fillForm([
                'source_type' => 'upload',
            ])
            ->assertFormFieldVisible('public_url')
            ->assertFormFieldVisible('mime_type')
            ->assertFormFieldVisible('extension')
            ->assertFormFieldVisible('size_bytes')
            ->assertFormFieldVisible('duration_seconds')
            ->assertFormFieldEnabled('public_url')
            ->assertFormFieldEnabled('mime_type')
            ->assertFormFieldEnabled('extension')
            ->assertFormFieldEnabled('size_bytes')
            ->assertFormFieldEnabled('duration_seconds');
    }

    public function test_embed_requires_valid_embed_url(): void
    {
        Livewire::actingAs(User::first())
            ->test(CreateVideo::class)
            ->fillForm([
                'source_type' => 'embed',
                'embed_url' => 'not-a-url',
                'title' => 'Bad Embed',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['embed_url']);
    }

    public function test_embed_rejects_non_supported_provider_hosts(): void
    {
        Livewire::actingAs(User::first())
            ->test(CreateVideo::class)
            ->fillForm([
                'source_type' => 'embed',
                'embed_url' => 'https://example.org/embed/123',
                'title' => 'Bad Provider',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['embed_url']);
    }

    public function test_embed_clears_upload_fields_on_create(): void
    {
        Livewire::actingAs(User::first())
            ->test(CreateVideo::class)
            ->fillForm([
                'source_type' => 'embed',
                'embed_url' => 'https://www.youtube.com/embed/abc123',
                'disk' => 'public',
                'path' => 'cms/videos/2026/02/example.mp4',
                'public_url' => 'https://cdn.example.org/example.mp4',
                'mime_type' => 'video/mp4',
                'extension' => 'mp4',
                'size_bytes' => 12345,
                'duration_seconds' => 22,
                'title' => 'Embed Video',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(Video::class, [
            'source_type' => 'embed',
            'embed_url' => 'https://www.youtube.com/embed/abc123',
            'path' => null,
            'public_url' => null,
            'mime_type' => null,
            'extension' => null,
            'size_bytes' => null,
            'duration_seconds' => null,
        ]);
    }

    public function test_upload_requires_exactly_one_source(): void
    {
        Livewire::actingAs(User::first())
            ->test(CreateVideo::class)
            ->fillForm([
                'source_type' => 'upload',
                'title' => 'No Source',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['path', 'external_file_url']);
    }

    public function test_upload_mode_clears_external_url_when_local_path_selected(): void
    {
        Livewire::actingAs(User::first())
            ->test(CreateVideo::class)
            ->fillForm([
                'source_type' => 'upload',
                'external_file_url' => 'https://cdn.example.org/video.mp4',
            ])
            ->fillForm([
                'path' => 'cms/videos/2026/02/local.mp4',
            ])
            ->assertFormSet([
                'external_file_url' => null,
            ]);
    }

    public function test_upload_mode_clears_local_path_when_external_url_selected(): void
    {
        Livewire::actingAs(User::first())
            ->test(CreateVideo::class)
            ->fillForm([
                'source_type' => 'upload',
                'path' => 'cms/videos/2026/02/local.mp4',
            ])
            ->fillForm([
                'external_file_url' => 'https://cdn.example.org/video.mp4',
            ])
            ->assertFormSet([
                'path' => null,
            ]);
    }

    public function test_upload_with_external_url_saves_and_clears_embed(): void
    {
        Livewire::actingAs(User::first())
            ->test(CreateVideo::class)
            ->fillForm([
                'source_type' => 'upload',
                'external_file_url' => 'https://cdn.example.org/hero-video.mp4',
                'embed_url' => 'https://www.youtube.com/embed/abc123',
                'title' => 'External Upload',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(Video::class, [
            'source_type' => 'upload',
            'public_url' => 'https://cdn.example.org/hero-video.mp4',
            'embed_url' => null,
            'disk' => null,
            'path' => null,
            'extension' => 'mp4',
        ]);
    }

    public function test_upload_rejects_unsupported_external_extension(): void
    {
        Livewire::actingAs(User::first())
            ->test(CreateVideo::class)
            ->fillForm([
                'source_type' => 'upload',
                'external_file_url' => 'https://cdn.example.org/hero-video.mov',
                'title' => 'Bad External Upload',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['external_file_url']);
    }

    public function test_upload_from_local_path_auto_populates_metadata(): void
    {
        Storage::disk('public')->put('cms/videos/2026/02/test-upload.mp4', str_repeat('a', 2048));

        Livewire::actingAs(User::first())
            ->test(CreateVideo::class)
            ->fillForm([
                'source_type' => 'upload',
                'path' => 'cms/videos/2026/02/test-upload.mp4',
                'title' => 'Local Upload',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $saved = Video::query()->where('title', 'Local Upload')->firstOrFail();
        $this->assertSame('upload', $saved->source_type);
        $this->assertSame('cms/videos/2026/02/test-upload.mp4', $saved->path);
        $this->assertNotNull($saved->public_url);
        $this->assertSame('mp4', $saved->extension);
        $this->assertNotNull($saved->size_bytes);
    }

    public function test_upload_rejects_unsupported_local_extension(): void
    {
        Storage::disk('public')->put('cms/videos/2026/02/test-upload.mov', str_repeat('b', 1024));

        Livewire::actingAs(User::first())
            ->test(CreateVideo::class)
            ->fillForm([
                'source_type' => 'upload',
                'path' => 'cms/videos/2026/02/test-upload.mov',
                'title' => 'Bad Local Upload',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['path']);
    }

    public function test_create_sets_created_by_to_authenticated_user(): void
    {
        $user = User::first();

        Livewire::actingAs($user)
            ->test(CreateVideo::class)
            ->fillForm([
                'source_type' => 'embed',
                'embed_url' => 'https://www.youtube.com/embed/xyz789',
                'title' => 'Ownership Test',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(Video::class, [
            'title' => 'Ownership Test',
            'created_by' => $user->id,
        ]);
    }

    public function test_edit_normalizes_when_switching_to_embed(): void
    {
        $video = Video::factory()->create([
            'source_type' => 'upload',
            'disk' => 'public',
            'path' => 'cms/videos/2026/02/original.mp4',
            'public_url' => 'https://cdn.example.org/original.mp4',
            'mime_type' => 'video/mp4',
            'extension' => 'mp4',
            'size_bytes' => 8000,
            'duration_seconds' => 30,
        ]);

        Livewire::actingAs(User::first())
            ->test(EditVideo::class, ['record' => $video->getRouteKey()])
            ->fillForm([
                'source_type' => 'embed',
                'embed_url' => 'https://vimeo.com/12345678',
                'title' => 'Edited Embed',
                'is_active' => true,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $video->refresh();
        $this->assertSame('embed', $video->source_type);
        $this->assertSame('https://vimeo.com/12345678', $video->embed_url);
        $this->assertNull($video->path);
        $this->assertNull($video->public_url);
        $this->assertNull($video->mime_type);
        $this->assertNull($video->size_bytes);
    }
}
