<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Stable, real pages for manual testing + predictable tests
        $pages = [
            [
                'title'     => 'Test translations Page',
                'is_active' => true,
            ],
            [
                'title'     => 'About Us',
                'is_active' => true,
            ],
            [
                'title'     => 'Donate',
                'is_active' => true,
            ],
            [
                'title'     => 'Events',
                'is_active' => true,
            ],
            [
                'title'     => 'Privacy Policy',
                'is_active' => true,
            ],
            [
                // Inactive page to confirm redirect / “not found” behavior
                'title'     => 'Internal Draft Page',
                'is_active' => false,
            ],
        ];

        foreach ($pages as $data) {
            Page::firstOrCreate(
                ['title' => $data['title']],
                $data
            );
        }

        // Optional: add a few random pages only in non-testing env
        if (! app()->environment('testing')) {
            Page::factory()->count(3)->create();
        }
    }
}
