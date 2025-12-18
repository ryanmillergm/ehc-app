<?php

namespace Database\Factories;

use App\Models\Pledge;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Transaction>
 */
class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        return [
            'pledge_id'         => null, // or Pledge::factory() if you want linked pledges by default
            'payment_intent_id' => 'pi_' . Str::random(24),
            'subscription_id'   => null,
            'charge_id'         => null,
            'customer_id'       => 'cus_' . Str::random(14),

            'amount_cents'      => $this->faker->numberBetween(1000, 10000),
            'currency'          => 'usd',

            'status'            => 'pending',

            'payer_email'       => $this->faker->safeEmail(),
            'payer_name'        => $this->faker->name(),
            'receipt_url'       => null,

            'source'            => 'test',
            'metadata'          => [],

            'paid_at'           => null,
        ];
    }

    /**
     * Convenience state for succeeded transactions
     */
    public function succeeded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'  => 'succeeded',
            'paid_at' => now(),
        ]);
    }

    /**
     * Convenience state for failed transactions
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'  => 'failed',
            'paid_at' => null,
        ]);
    }
}
