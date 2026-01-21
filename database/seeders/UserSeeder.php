<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->updateOrCreate(
            ['email' => 'ryanmillergm@gmail.com'],
            [
                'first_name' => 'Ryan',
                'last_name'  => 'Miller',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ],
        );

        $user2 = User::query()->updateOrCreate(
            ['email' => 'breadofgraceministry@gmail.com'],
            [
                'first_name' => 'Cindy',
                'last_name'  => 'Dewey',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ],
        );

        $role = Role::query()->where('name', 'Super Admin')->firstOrFail();

        $user->assignRole($role);
        $user2->assignRole($role);
    }
}
