<?php

namespace Database\Seeders;

use App\Models\Child;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

class ChildSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create();

        $child = Child::factory([
            'first_name'    => $faker->firstName(),
            'last_name'     => $faker->lastName(),
            'date_of_birth' => $faker->date('Y_m_d'),
            'country'       => $faker->country(),
            'city'          => $faker->city(),
            'description'   => $faker->paragraph(1),
            'team_id'       => $faker->numberBetween(1, 3),
        ])
            ->create();
    }
}
