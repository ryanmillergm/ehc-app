<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Jetstream\Features;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password = null;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => $this->faker->firstName(),
            'last_name'  => $this->faker->lastName(),
            'email'      => $this->faker->unique()->safeEmail(),
            'email_verified_at' => now(),

            'password' => static::$password ??= Hash::make('password'),

            'two_factor_secret'         => null,
            'two_factor_recovery_codes' => null,
            'remember_token'            => Str::random(10),

            'profile_photo_path' => null,
            'current_team_id'    => null,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user should have a Jetstream personal team.
     */
    public function withPersonalTeam(callable $callback = null): static
    {
        if (! Features::hasTeamFeatures()) {
            return $this->state([]);
        }

        return $this->has(
            Team::factory()
                ->state(function (array $attributes, User $user) {
                    $first = (string) $user->first_name;
                    $last  = (string) $user->last_name;

                    return [
                        'name'    => "{$first} {$last}'s Team",
                        'user_id' => $user->id,
                        'slug'    => strtolower($first) . '-' . strtolower($last) . 's-team',
                    ];
                })
                ->when(is_callable($callback), $callback),
            'ownedTeams'
        );
    }

    /**
     * Convenience state: create and attach "assigned teams" (for your app's relation).
     *
     * This assumes your User model has an assignedTeams() belongsToMany relation.
     */
    public function hasAssignedTeams(int $count = 1): static
    {
        return $this->afterCreating(function (User $user) use ($count) {
            // If your app doesn't have assignedTeams(), remove this method.
            if (! method_exists($user, 'assignedTeams')) {
                return;
            }

            $teams = Team::factory()
                ->count($count)
                ->create([
                    'user_id' => $user->id,
                ]);

            $user->assignedTeams()->syncWithoutDetaching($teams->modelKeys());
        });
    }
}
