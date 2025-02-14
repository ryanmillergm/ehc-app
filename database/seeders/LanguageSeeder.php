<?php

namespace Database\Seeders;

use App\Models\Language;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class LanguageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Language::create([
            'title'         => 'English',
            'iso_code'      => 'en',
            'locale'        => 'en',
            'right_to_left' => false,
        ]);
        Language::create([
            'title'         => 'Spanish',
            'iso_code'      => 'es',
            'locale'        => 'es',
            'right_to_left' => false,
        ]);
        Language::create([
            'title'         => 'French',
            'iso_code'      => 'fr',
            'locale'        => 'fr',
            'right_to_left' => false,
        ]);
        Language::create([
            'title'         => 'Arabic',
            'iso_code'      => 'ar',
            'locale'        => 'ar',
            'right_to_left' => true,
        ]);
    }
}
