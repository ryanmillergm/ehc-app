<?php

namespace Database\Factories;

use App\Models\EmailList;
use App\Models\EmailSubscriber;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\EmailSubscriber>
 */
class EmailSubscriberFactory extends Factory
{
    protected $model = EmailSubscriber::class;

    public function definition(): array
    {
        $email = $this->faker->unique()->safeEmail();

        return [
            'email' => $email,
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),

            'user_id' => null,
            'preferences' => null,

            'unsubscribe_token' => Str::random(64),

            // default: opted in globally
            'subscribed_at' => now(),
            'unsubscribed_at' => null,

            // let the model's saving() hook set email_canonical
            'email_canonical' => null,
        ];
    }

    public function forUser(?User $user = null): static
    {
        return $this->state(fn () => [
            'user_id' => $user?->id ?? User::factory(),
        ]);
    }

    public function globallyUnsubscribed(): static
    {
        return $this->state(fn () => [
            'unsubscribed_at' => now(),
        ]);
    }

    public function notYetOptedIn(): static
    {
        return $this->state(fn () => [
            'subscribed_at' => null,
            'unsubscribed_at' => null,
        ]);
    }

    /**
     * Attach a list (creates one if not provided) and mark pivot subscribed.
     */
    public function subscribedToList(?EmailList $list = null): static
    {
        return $this->afterCreating(function (EmailSubscriber $subscriber) use ($list) {
            $list ??= EmailList::factory()->create();

            // Attach if not already attached
            if (! $subscriber->lists()->whereKey($list->id)->exists()) {
                $subscriber->lists()->attach($list->id, [
                    'subscribed_at' => now(),
                    'unsubscribed_at' => null,
                ]);
            }
        });
    }

    /**
     * Attach a list and mark pivot unsubscribed.
     */
    public function unsubscribedFromList(?EmailList $list = null): static
    {
        return $this->afterCreating(function (EmailSubscriber $subscriber) use ($list) {
            $list ??= EmailList::factory()->create();

            if (! $subscriber->lists()->whereKey($list->id)->exists()) {
                $subscriber->lists()->attach($list->id, [
                    'subscribed_at' => now()->subDay(),
                    'unsubscribed_at' => now(),
                ]);
            } else {
                $subscriber->lists()->updateExistingPivot($list->id, [
                    'unsubscribed_at' => now(),
                ]);
            }
        });
    }
}
