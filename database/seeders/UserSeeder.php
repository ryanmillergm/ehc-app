<?php

namespace Database\Seeders;

// use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::factory([
            'first_name'    => 'Ryan',
            'last_name'     => 'Miller',
            'email'         => 'ryanmillergm@gmail.com'
        ])
            ->create();

        $user2 = User::factory([
            'first_name'    => 'test',
            'last_name'     => 'eleven',
            'email'         => 'test11@test.com',
        ])
            ->create();

        User::factory()
            ->count(10)
            ->hasAssignedTeams(1)
            ->create();
    }
}
