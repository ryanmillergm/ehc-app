<?php

namespace Tests\Feature\Stripe;

use App\Models\Pledge;
use App\Models\Transaction;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Stripe\Invoice;
use Stripe\PaymentIntent;
use Stripe\StripeClient;
use Stripe\Subscription;
use Tests\TestCase;

class AdoptOwnerTransactionForSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_adopts_the_existing_payment_intent_owner_transaction_instead_of_updating_a_placeholder(): void
    {
        config([
            'services.stripe.secret' => 'sk_test_dummy',
            'services.stripe.debug_state' => false,
        ]);

        $user = User::factory()->create();

        $pledge = Pledge::factory()->create([
            'user_id'                => $user->id,
            'status'                 => 'active',
            'attempt_id'             => 'attempt_test_123',
            'stripe_customer_id'     => 'cus_test_123',
            'stripe_price_id'        => 'price_test_123',
            'stripe_subscription_id' => null,
            'interval'               => 'month',
            'currency'               => 'usd',
            'amount_cents'           => 200,
            'donor_email'            => 'donor@example.com',
            'donor_name'             => 'Donor Person',
        ]);

        // PI owner row already exists (e.g. webhook created it first)
        $owner = Transaction::factory()->create([
            'user_id'           => $user->id,
            'pledge_id'         => $pledge->id,
            'payment_intent_id' => 'pi_test_123',
            'charge_id'         => null,
            'subscription_id'   => null,
            'customer_id'       => $pledge->stripe_customer_id,
            'status'            => 'pending',
            'type'              => 'subscription_initial',
            'source'            => 'donation_widget',
            'attempt_id'        => null,
        ]);

        // Placeholder created by "complete" keyed by attempt_id
        $placeholder = Transaction::factory()->create([
            'user_id'           => $user->id,
            'pledge_id'         => $pledge->id,
            'payment_intent_id' => null,
            'charge_id'         => null,
            'subscription_id'   => null,
            'customer_id'       => $pledge->stripe_customer_id,
            'status'            => 'pending',
            'type'              => 'subscription_initial',
            'source'            => 'donation_widget',
            'attempt_id'        => $pledge->attempt_id,
        ]);

        // -------- Stripe fakes (NO magic getService / __get) --------
        $stripe = Mockery::mock(StripeClient::class);

        $subscriptions = Mockery::mock();
        $invoices      = Mockery::mock();
        $paymentIntents = Mockery::mock();

        // Attach services as properties so `$this->stripe->subscriptions->create()` works cleanly.
        $stripe->subscriptions  = $subscriptions;
        $stripe->invoices       = $invoices;
        $stripe->paymentIntents = $paymentIntents;

        $now = time();

        // PaymentIntent owner guard will call retrieve() because paymentIntents is present+object.
        $paymentIntents->shouldReceive('retrieve')
            ->once()
            ->with('pi_test_123', Mockery::any())
            ->andReturn(PaymentIntent::constructFrom([
                'id' => 'pi_test_123',
                'customer' => 'cus_test_123',
                'latest_charge' => 'ch_test_123',
                'status' => 'succeeded',
            ]));

        $invoice = Invoice::constructFrom([
            'id' => 'in_test_123',
            'paid' => true,
            'amount_paid' => 200,
            'currency' => 'usd',
            'payment_intent' => 'pi_test_123',
            'charge' => 'ch_test_123',
            'status_transitions' => [
                'paid_at' => $now,
            ],
            'lines' => [
                'data' => [
                    [
                        'period' => [
                            'start' => $now - 60,
                            'end'   => $now + 3600,
                        ],
                    ],
                ],
            ],
            'hosted_invoice_url' => 'https://example.test/invoice',
            'customer' => 'cus_test_123',
        ]);

        $subscription = Subscription::constructFrom([
            'id' => 'sub_test_123',
            'status' => 'active',
            'cancel_at_period_end' => false,
            'current_period_start' => $now - 60,
            'current_period_end'   => $now + 3600,
            'customer' => 'cus_test_123',
            'items' => [
                'data' => [
                    [
                        'id' => 'si_test_1',
                        'current_period_start' => $now - 60,
                        'current_period_end'   => $now + 3600,
                        'price' => [
                            'id' => 'price_test_123',
                            'product' => 'prod_test_123',
                        ],
                    ],
                ],
            ],
            'latest_invoice' => $invoice,
        ]);

        $subscriptions->shouldReceive('create')
            ->once()
            ->andReturn($subscription);

        // This should not be needed with our invoice object having enough data.
        $invoices->shouldReceive('retrieve')->never();

        $service = new StripeService($stripe);

        // Act
        $service->createSubscriptionForPledge($pledge, 'pm_test_123');

        // Assert: owner gets updated, placeholder does NOT claim PI
        $owner->refresh();
        $placeholder->refresh();

        $this->assertSame('pi_test_123', $owner->payment_intent_id);
        $this->assertSame('ch_test_123', $owner->charge_id);
        $this->assertSame('sub_test_123', $owner->subscription_id);
        $this->assertSame('succeeded', $owner->status);

        $this->assertNull($placeholder->payment_intent_id);

        $this->assertSame(1, Transaction::where('payment_intent_id', 'pi_test_123')->count());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
