<?php

namespace Tests\Feature\Database;

use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PermissionSeederTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_seeds_pages_render_unsafe_html_permission(): void
    {
        $this->seed([
            PermissionSeeder::class,
        ]);

        $this->assertDatabaseHas('permissions', [
            'name' => 'pages.render_unsafe_html',
            'guard_name' => 'web',
        ]);

        $permission = Permission::query()->where('name', 'pages.render_unsafe_html')->first();
        $this->assertNotNull($permission);
    }
}

