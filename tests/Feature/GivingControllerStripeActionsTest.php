<?php

namespace Tests\Feature;

use App\Models\Pledge;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class GivingControllerStripeActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_cancel_subscription_calls_stripe_service_for_own_pledge(): void
    {
        $user = User::factory()->create();

        $pledge = Pledge::forceCreate([
            'user_id'                => $user->id,
            'stripe_subscription_id' => 'sub_123',
            'amount_cents'           => 1000,
            'currency'               => 'usd',
            'interval'               => 'month',
            'status'                 => 'active',
            'cancel_at_period_end'   => false,
        ]);

        $stripe = Mockery::mock(StripeService::class);
        $stripe
            ->shouldReceive('cancelSubscriptionAtPeriodEnd')
            ->once()
            ->with(Mockery::on(fn ($p) => $p->id === $pledge->id));

        // Bind mocked service into the container
        $this->app->instance(StripeService::class, $stripe);

        $this->actingAs($user)
            ->post(route('giving.subscriptions.cancel', $pledge))
            ->assertRedirect();

        $this->assertEquals(
            'Your subscription will be cancelled at the end of the current period.',
            session('status')
        );
    }

    public function test_cancel_subscription_for_other_users_pledge_is_forbidden(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $pledge = Pledge::forceCreate([
            'user_id'                => $owner->id,
            'stripe_subscription_id' => 'sub_999',
            'amount_cents'           => 1000,
            'currency'               => 'usd',
            'interval'               => 'month',
            'status'                 => 'active',
            'cancel_at_period_end'   => false,
        ]);

        // Don’t expect Stripe to be called at all here
        $stripe = Mockery::mock(StripeService::class);
        $stripe->shouldReceive('cancelSubscriptionAtPeriodEnd')->never();

        $this->app->instance(StripeService::class, $stripe);

        $this->actingAs($other)
            ->post(route('giving.subscriptions.cancel', $pledge))
            ->assertForbidden();
    }

    public function test_update_subscription_amount_calls_stripe_with_correct_cents(): void
    {
        $user = User::factory()->create();

        $pledge = Pledge::forceCreate([
            'user_id'                => $user->id,
            'stripe_subscription_id' => 'sub_456',
            'stripe_price_id'        => 'price_123',
            'amount_cents'           => 1000,
            'currency'               => 'usd',
            'interval'               => 'month',
            'status'                 => 'active',
            'cancel_at_period_end'   => false,
        ]);

        $newAmountDollars = 25.00;
        $expectedCents    = 2500;

        $stripe = Mockery::mock(StripeService::class);
        $stripe
            ->shouldReceive('updateSubscriptionAmount')
            ->once()
            ->with(
                Mockery::on(fn ($p) => $p->id === $pledge->id),
                $expectedCents
            );

        $this->app->instance(StripeService::class, $stripe);

        $this->actingAs($user)
            ->post(route('giving.subscriptions.amount', $pledge), [
                'amount_dollars' => $newAmountDollars,
            ])
            ->assertRedirect();

        $this->assertEquals(
            'Your monthly amount has been updated.',
            session('status')
        );
    }
}
