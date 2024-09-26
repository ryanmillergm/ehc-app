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
            'iso_code'      => 'en',
            'locale'        => 'en',
            'right_to_left' => false,
        ]);
    }
}
