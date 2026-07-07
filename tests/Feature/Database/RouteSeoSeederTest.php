<?php

namespace Tests\Feature\Database;

use App\Models\Language;
use App\Models\SeoMeta;
use App\Support\Seo\RouteSeoTarget;
use Database\Seeders\RouteSeoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RouteSeoSeederTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function route_seo_seeder_persists_robots_for_all_route_targets(): void
    {
        $language = Language::factory()->english()->create();

        $this->seed([RouteSeoSeeder::class]);

        foreach (array_keys(RouteSeoTarget::options()) as $routeKey) {
            $this->assertDatabaseHas('seo_meta', [
                'seoable_type' => 'route',
                'seoable_id' => 0,
                'target_key' => $routeKey,
                'language_id' => $language->id,
                'robots' => 'index,follow',
                'is_active' => true,
            ]);
        }
    }

    #[Test]
    public function route_seo_seeder_is_idempotent_for_route_rows(): void
    {
        Language::factory()->english()->create();
        Language::factory()->spanish()->create();

        $this->seed([RouteSeoSeeder::class]);

        $countAfterFirst = SeoMeta::query()
            ->where('seoable_type', 'route')
            ->where('seoable_id', 0)
            ->count();

        $this->seed([RouteSeoSeeder::class]);

        $countAfterSecond = SeoMeta::query()
            ->where('seoable_type', 'route')
            ->where('seoable_id', 0)
            ->count();

        $this->assertSame($countAfterFirst, $countAfterSecond);
    }
}
