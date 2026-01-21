<?php

namespace Database\Seeders;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TeamSeeder extends Seeder
{
    public function run(): void
    {
        $ryan = User::query()->where('email', 'ryanmillergm@gmail.com')->first();

        if (! $ryan) {
            // If UserSeeder didn’t run for some reason, create Ryan
            $ryan = User::query()->create([
                'first_name' => 'Ryan',
                'last_name'  => 'Miller',
                'email'      => 'ryanmillergm@gmail.com',
                'email_verified_at' => now(),
                'password' => bcrypt('password'),
            ]);
        }

        $name = "{$ryan->first_name} {$ryan->last_name}'s Team";

        $team = Team::query()->updateOrCreate(
            ['slug' => Str::slug($ryan->first_name . '-' . $ryan->last_name . '-team')],
            [
                'user_id' => $ryan->id,
                'name'    => $name,
            ]
        );

        // Optional: attach as assigned team if your app uses that pivot relation
        if (method_exists($ryan, 'assignedTeams')) {
            $ryan->assignedTeams()->syncWithoutDetaching([$team->id]);
        }
    }
}
