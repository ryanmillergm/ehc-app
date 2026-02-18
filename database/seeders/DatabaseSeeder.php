<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            UserSeeder::class,
            TeamSeeder::class,
            // ChildSeeder::class,
            LanguageSeeder::class,
            ImageTypeSeeder::class,
            ImageSeeder::class,
            SiteMediaDefaultSeeder::class,
            HomePageContentSeeder::class,
            HomeSectionSeeder::class,
            FaqItemSeeder::class,
            // PageSeeder::class,
            // PageTranslationSeeder::class,
            EmailListSeeder::class,
            ApplicationFormSeeder::class,
            VolunteerNeedSeeder::class,
            // VolunteerApplicationSeeder::class,
        ]);
    }
}
