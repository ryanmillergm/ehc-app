<?php

namespace Tests\Feature\Http;

use App\Models\Pledge;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class GivingControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_user_can_cancel_own_subscription_at_period_end(): void
    {
        $user = User::factory()->create();

        $pledge = Pledge::forceCreate([
            'user_id'                => $user->id,
            'stripe_subscription_id' => 'sub_own_123',
            'amount_cents'           => 1500,
            'currency'               => 'usd',
            'interval'               => 'month',
            'status'                 => 'active',
            'cancel_at_period_end'   => false,
        ]);

        // Mock StripeService so we don't hit real Stripe
        $stripe = Mockery::mock(StripeService::class);
        $stripe
            ->shouldReceive('cancelSubscriptionAtPeriodEnd')
            ->once()
            ->with(Mockery::on(function ($arg) use ($pledge) {
                // ensure the same pledge instance is passed
                return $arg->is($pledge);
            }));

        $this->app->instance(StripeService::class, $stripe);

        $response = $this
            ->actingAs($user)
            ->post(route('giving.subscriptions.cancel', $pledge));

        $response->assertRedirect();
        $response->assertSessionHas('status', 'Your subscription will be cancelled at the end of the current period.');
    }

    public function test_user_cannot_cancel_someone_elses_pledge(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $pledge = Pledge::forceCreate([
            'user_id'                => $owner->id,
            'stripe_subscription_id' => 'sub_other_123',
            'amount_cents'           => 1500,
            'currency'               => 'usd',
            'interval'               => 'month',
            'status'                 => 'active',
            'cancel_at_period_end'   => false,
        ]);

        $stripe = Mockery::mock(StripeService::class);
        $stripe
            ->shouldReceive('cancelSubscriptionAtPeriodEnd')
            ->never();

        $this->app->instance(StripeService::class, $stripe);

        $response = $this
            ->actingAs($other)
            ->post(route('giving.subscriptions.cancel', $pledge));

        $response->assertStatus(403);
    }

    public function test_user_can_update_subscription_amount(): void
    {
        $user = User::factory()->create();

        $pledge = Pledge::forceCreate([
            'user_id'                => $user->id,
            'stripe_subscription_id' => 'sub_update_123',
            'stripe_price_id'        => 'price_abc',
            'amount_cents'           => 1500,
            'currency'               => 'usd',
            'interval'               => 'month',
            'status'                 => 'active',
            'cancel_at_period_end'   => false,
        ]);

        $stripe = Mockery::mock(StripeService::class);
        $stripe
            ->shouldReceive('updateSubscriptionAmount')
            ->once()
            ->with(
                Mockery::on(fn ($arg) => $arg->is($pledge)),
                2500 // 25.00 dollars → 2500 cents
            );

        $this->app->instance(StripeService::class, $stripe);

        $response = $this
            ->actingAs($user)
            ->post(route('giving.subscriptions.amount', $pledge), [
                'amount_dollars' => 25,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('status', 'Your monthly amount has been updated.');
    }

    public function test_update_subscription_amount_requires_valid_amount(): void
    {
        $user = User::factory()->create();

        $pledge = Pledge::forceCreate([
            'user_id'                => $user->id,
            'stripe_subscription_id' => 'sub_update_invalid',
            'amount_cents'           => 1500,
            'currency'               => 'usd',
            'interval'               => 'month',
            'status'                 => 'active',
            'cancel_at_period_end'   => false,
        ]);

        $stripe = Mockery::mock(StripeService::class);
        $stripe
            ->shouldReceive('updateSubscriptionAmount')
            ->never();

        $this->app->instance(StripeService::class, $stripe);

        $response = $this
            ->from(route('giving.index')) // so we can assert redirect back w/ errors
            ->actingAs($user)
            ->post(route('giving.subscriptions.amount', $pledge), [
                'amount_dollars' => 0, // invalid (min:1)
            ]);

        $response
            ->assertRedirect(route('giving.index'))
            ->assertSessionHasErrors('amount_dollars');
    }
}
