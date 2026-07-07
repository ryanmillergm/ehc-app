<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Images\Pages\CreateImage;
use App\Models\Image;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ImageResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed('PermissionSeeder');
        $this->signInWithPermissions(null, ['admin.panel']);
    }

    public function test_alt_text_is_required_when_image_is_not_decorative(): void
    {
        Livewire::actingAs(User::first())
            ->test(CreateImage::class)
            ->fillForm([
                'path' => ['cms/images/2026/02/non-decorative.jpg'],
                'disk' => 'public',
                'public_url' => 'https://example.test/storage/cms/images/2026/02/non-decorative.jpg',
                'is_decorative' => false,
                'alt_text' => null,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['alt_text' => 'required']);
    }

    public function test_alt_text_is_optional_when_image_is_decorative(): void
    {
        Livewire::actingAs(User::first())
            ->test(CreateImage::class)
            ->fillForm([
                'path' => ['cms/images/2026/02/decorative.jpg'],
                'disk' => 'public',
                'public_url' => 'https://example.test/storage/cms/images/2026/02/decorative.jpg',
                'is_decorative' => true,
                'alt_text' => null,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(Image::class, [
            'path' => 'cms/images/2026/02/decorative.jpg',
            'is_decorative' => true,
        ]);
    }

    public function test_image_disk_is_forced_to_public_even_if_payload_attempts_override(): void
    {
        Livewire::actingAs(User::first())
            ->test(CreateImage::class)
            ->fillForm([
                'path' => ['cms/images/2026/02/disk-lock.jpg'],
                'disk' => 's3',
                'public_url' => 'https://example.test/storage/cms/images/2026/02/disk-lock.jpg',
                'is_decorative' => true,
                'alt_text' => null,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(Image::class, [
            'path' => 'cms/images/2026/02/disk-lock.jpg',
            'disk' => 'public',
        ]);
    }
}
