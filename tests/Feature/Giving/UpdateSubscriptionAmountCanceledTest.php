<?php

namespace Tests\Feature\Giving;

use App\Models\Pledge;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Stripe\StripeClient;
use Tests\TestCase;

class UpdateSubscriptionAmountCanceledTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_fails_gracefully_if_stripe_subscription_is_canceled_and_syncs_pledge_status(): void
    {
        // Ensure the StripeClient singleton doesn't explode in the container.
        config(['services.stripe.secret' => 'sk_test_dummy']);

        $user = User::factory()->create();
        $this->actingAs($user);

        $pledge = Pledge::factory()->create([
            'user_id'               => $user->id,
            'status'                => 'active', // stale local state
            'stripe_subscription_id'=> 'sub_test_123',
            'stripe_customer_id'    => 'cus_test_123',
            'stripe_price_id'       => 'price_test_123',
            'interval'              => 'month',
            'currency'              => 'usd',
            'amount_cents'          => 1000,
        ]);

        // Mock StripeClient calls used by StripeService::updateSubscriptionAmount.
        $stripe = Mockery::mock(StripeClient::class);
        $this->app->instance(StripeClient::class, $stripe);

        // subscriptions->retrieve(...)
        $canceledSub = (object) [
            'id' => 'sub_test_123',
            'status' => 'canceled',
            'cancel_at_period_end' => false,
            'canceled_at' => time(),
            'items' => (object) [
                'data' => [
                    (object) [
                        'id' => 'si_test_1',
                        'current_period_start' => time() - 3600,
                        'current_period_end' => time() + 3600,
                        'price' => (object) [
                            'id' => 'price_test_123',
                            'product' => 'prod_test_123',
                        ],
                    ],
                ],
            ],
        ];

        // StripeClient uses magic __get("subscriptions") and then calls ->retrieve().
        // Easiest approach: mock getService('subscriptions') which is how stripe-php resolves services internally.
        $subsService = Mockery::mock();
        $stripe->shouldReceive('getService')->with('subscriptions')->andReturn($subsService);
        $subsService->shouldReceive('retrieve')->once()->andReturn($canceledSub);

        // Route: adjust if your route differs.
        $res = $this->from('/giving')
            ->post(route('giving.subscriptions.amount', $pledge), [
                'amount_dollars' => 20,
            ]);

        // The controller doesn't catch ValidationException explicitly, so Laravel converts it to a redirect with errors.
        $res->assertRedirect('/giving');
        $res->assertSessionHasErrors(['amount_dollars']);

        $pledge->refresh();
        $this->assertSame('canceled', $pledge->status);
    }
}
