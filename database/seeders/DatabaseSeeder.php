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
            ChildSeeder::class,
            LanguageSeeder::class,
            PageSeeder::class,
            PageTranslationSeeder::class,
            EmailListSeeder::class,
        ]);
    }
}
