<?php

namespace Database\Seeders;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

class TeamSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create();

        $user = User::where('email', 'ryanmillergm@gmail.com')->get()->first() ?? User::factory([
            'first_name' => 'Ryan',
            'last_name' => 'Miller',
            'email' => 'ryanmillergm@gmail.com'
        ])->create();

        $team = Team::factory([
            'user_id' => $user->id,
            'name' => $user->first_name . ' ' . $user->last_name . "'s Team",
            'slug' => strtolower( $user->first_name) . '-' . strtolower($user->last_name) . "s-team",
        ])->create();


        Team::factory([
            'user_id' => $user->id,
            'name' => $user->first_name . ' ' . $user->last_name . "'s Team",
            'slug' => strtolower($faker->firstName()) . '-' . strtolower($faker->lastName()) . "s-team",
            ])->create();

        $user->assignedTeams()->attach($team);
    }
}
