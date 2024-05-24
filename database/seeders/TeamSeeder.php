<?php

namespace Database\Seeders;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TeamSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::where('email', 'ryanmillergm@gmail.com')->get()->first() ?? User::factory([
            'first_name' => 'Ryan',
            'last_name' => 'Miller',
            'email' => 'ryanmillergm@gmail.com'
        ])->create();

        Team::factory([
            'user_id' => $user->id,
            'name' => $user->first_name . ' ' . $user->last_name . "'s Team",
        ])->create();

    }
}
