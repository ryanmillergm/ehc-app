<?php

namespace Database\Factories;

use App\Models\Pledge;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PledgeFactory extends Factory
{
    protected $model = Pledge::class;

    public function definition(): array
    {
        return [
            'stripe_subscription_id' => 'sub_' . Str::random(24),
            'stripe_customer_id'     => 'cus_' . Str::random(14),
            'stripe_price_id'        => 'price_' . Str::random(24),

            'amount_cents'           => $this->faker->numberBetween(1000, 10000),
            'currency'               => 'usd',
            'interval'               => 'month',

            'status'                 => 'active',
            'cancel_at_period_end'   => false,
            'current_period_start'   => now()->subMonth(),
            'current_period_end'     => now()->addMonth(),

            'last_pledge_at'         => now()->subMonth(),
            'next_pledge_at'         => now()->addMonth(),
            'latest_invoice_id'      => 'in_' . Str::random(24),
            'latest_payment_intent_id' => 'pi_' . Str::random(24),

            'donor_email'            => $this->faker->safeEmail(),
            'donor_name'             => $this->faker->name(),

            'metadata'               => [],
        ];
    }
}
