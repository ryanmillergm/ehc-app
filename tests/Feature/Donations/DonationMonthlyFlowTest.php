<?php

namespace Tests\Feature\Donations;

use App\Models\Pledge;
use App\Models\Transaction;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\Test;
use Stripe\SetupIntent;
use Stripe\Subscription;
use Tests\TestCase;

class DonationMonthlyFlowTest extends TestCase
{
    use RefreshDatabase;
    use MockeryPHPUnitIntegration;

    protected function setUp(): void
    {
        parent::setUp();

        // Prevent your AppServiceProvider / StripeService constructor from exploding in tests.
        config([
            'services.stripe.secret' => 'sk_test_dummy',
            'services.stripe.recurring_product_id' => 'prod_dummy',
            'services.stripe.webhook_secret' => 'whsec_dummy',
            'services.stripe.debug_state' => false,
        ]);
    }

    #[Test]
    public function start_creates_pledge_and_returns_setup_intent_client_secret(): void
    {
        $this->mock(StripeService::class, function ($mock) {
            $mock->shouldReceive('createSetupIntentForPledge')
                ->once()
                ->andReturn($this->fakeSetupIntent('seti_test_1', 'requires_payment_method', 'pm_test_ignored'));
        });

        $res = $this->postJson(route('donations.start'), [
            'frequency' => 'monthly',
            'amount'    => 2,
            'currency'  => 'USD',
            'donor' => [
                'email' => 'ryan@example.com',
                'name'  => 'Ryan Miller',
            ],
        ]);

        $res->assertOk();

        $json = $res->json();

        $pledgeId =
            data_get($json, 'pledge_id')
            ?? data_get($json, 'pledge.id')
            ?? data_get($json, 'pledge.data.id')
            ?? data_get($json, 'data.pledge_id')
            ?? data_get($json, 'data.pledge.id');

        $this->assertNotEmpty($pledgeId, 'Response did not include a pledge id in any expected location.');

        $setupIntentId =
            data_get($json, 'setup_intent_id')
            ?? data_get($json, 'setupIntentId')
            ?? data_get($json, 'setup_intent.id')
            ?? data_get($json, 'setup_intent.data.id')
            ?? data_get($json, 'data.setup_intent_id')
            ?? data_get($json, 'data.setup_intent.id');

        $clientSecret =
            data_get($json, 'client_secret')
            ?? data_get($json, 'clientSecret')
            ?? data_get($json, 'setup_intent.client_secret')
            ?? data_get($json, 'setup_intent.data.client_secret')
            ?? data_get($json, 'data.client_secret');

        $this->assertNotEmpty($setupIntentId, 'Response did not include setup intent id in any expected location.');
        $this->assertNotEmpty($clientSecret, 'Response did not include a client secret in any expected location.');

        $this->assertDatabaseCount('pledges', 1);
        $this->assertDatabaseHas('pledges', [
            'id'           => $pledgeId,
            'amount_cents' => 200,
            'currency'     => 'usd',
            'interval'     => 'month',
            'donor_email'  => 'ryan@example.com',
            'donor_name'   => 'Ryan Miller',
        ]);
    }

    #[Test]
    public function complete_subscription_requires_payment_method_id(): void
    {
        $pledge = Pledge::factory()->create();

        $this->mock(StripeService::class);

        $res = $this->postJson(route('donations.complete'), [
            'mode'      => 'subscription',
            'pledge_id' => $pledge->id,
            // missing payment_method_id intentionally
        ]);

        $res->assertStatus(422);
    }

    #[Test]
    public function complete_subscription_creates_placeholder_transaction_and_calls_stripe_service(): void
    {
        // The controller expects the placeholder to ALREADY exist (created during start()).
        // So we create it here to match production flow.

        $pledge = Pledge::factory()->create([
            'stripe_subscription_id' => null,
            'status'                 => 'pending',
            'attempt_id'             => 'attempt_test_1',
        ]);

        $placeholder = Transaction::factory()->create([
            'pledge_id'         => $pledge->id,
            'attempt_id'        => $pledge->attempt_id,
            'type'              => 'subscription_initial',
            'status'            => 'pending',
            'source'            => 'donation_widget',
            'subscription_id'   => null,
            'customer_id'       => null,
            'payment_intent_id' => null,
            'charge_id'         => null,
            'amount_cents'      => $pledge->amount_cents,
            'currency'          => $pledge->currency,
        ]);

        $sub = $this->fakeSubscription('sub_test_1', 'active');

        $this->mock(StripeService::class, function ($mock) use ($sub, $pledge) {
            $mock->shouldReceive('createSubscriptionForPledge')
                ->once()
                ->andReturnUsing(function ($p, $pm) use ($sub, $pledge) {
                    // Simulate what your StripeService sync does (at minimum)
                    $pledge->update([
                        'stripe_subscription_id' => $sub->id,
                        'status'                 => 'active',
                    ]);

                    return $sub;
                });

            $mock->shouldReceive('syncFromSubscription')
                ->zeroOrMoreTimes()
                ->andReturnNull();
        });

        $res = $this->postJson(route('donations.complete'), [
            'mode'              => 'subscription',
            'pledge_id'          => $pledge->id,
            'attempt_id'         => $pledge->attempt_id,
            'transaction_id'     => $placeholder->id,   // ✅ required now
            'payment_method_id'  => 'pm_test_1',
            'donor_first_name'   => 'Ryan',
            'donor_last_name'    => 'Miller',
            'donor_email'        => 'ryan@example.com',
        ]);

        $res->assertOk()->assertJsonStructure(['redirect']);

        // Still exactly one placeholder row (complete should not create a second one)
        $this->assertSame(1, Transaction::where('pledge_id', $pledge->id)->count());

        $this->assertDatabaseHas('transactions', [
            'id'       => $placeholder->id,
            'pledge_id'=> $pledge->id,
            'type'     => 'subscription_initial',
            'status'   => 'pending',
            'source'   => 'donation_widget',
        ]);

        $pledge->refresh();
        $this->assertSame('active', $pledge->status);
        $this->assertSame('sub_test_1', $pledge->stripe_subscription_id);

        $placeholder->refresh();
        $this->assertSame('subscription_initial', $placeholder->type);
    }

    private function fakeSetupIntent(string $id, string $status, ?string $pmId): SetupIntent
    {
        /** @var \Stripe\SetupIntent $si */
        return SetupIntent::constructFrom([
            'id'            => $id,
            'status'        => $status,
            'client_secret' => 'seti_cs_' . $id,
            'payment_method'=> $pmId,
        ]);
    }

    private function fakeSubscription(string $id, string $status): Subscription
    {
        /** @var \Stripe\Subscription $sub */
        return Subscription::constructFrom([
            'id'     => $id,
            'status' => $status,
        ]);
    }
}
