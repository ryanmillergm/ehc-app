<?php

namespace Tests\Unit;

use App\Models\Pledge;
use App\Models\Refund;
use App\Models\Transaction;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Stripe\StripeClient;
use Stripe\Subscription as StripeSubscription;
use Tests\TestCase;

class StripeServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_create_subscription_for_pledge_uses_existing_customer_and_price_and_creates_initial_transaction(): void
    {
        // Keep FK happy by using a nullable user_id
        $pledge = Pledge::forceCreate([
            'user_id'            => null,
            'stripe_customer_id' => 'cus_123',
            'stripe_price_id'    => 'price_123',
            'amount_cents'       => 1000,
            'currency'           => 'usd',
            'interval'           => 'month',
            'status'             => 'incomplete',
            'donor_email'        => 'donor@example.test',
            'donor_name'         => 'Test Donor',
        ]);

        // Real Stripe\Subscription instance via constructFrom
        $subscription = StripeSubscription::constructFrom([
            'id'                   => 'sub_999',
            'status'               => 'active',
            'current_period_start' => 1_700_000_000,
            'current_period_end'   => 1_702_592_000,
            'latest_invoice'       => [
                'id'                 => 'in_999',
                'amount_paid'        => 1000,
                'hosted_invoice_url' => 'https://example.test/invoices/in_999',
                'payment_intent'     => [
                    'id'            => 'pi_999',
                    'latest_charge' => 'ch_999',
                ],
            ],
        ], null);

        $subscriptions = Mockery::mock();
        $subscriptions
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($params) use ($pledge) {
                return $params['customer'] === $pledge->stripe_customer_id
                    && $params['items'][0]['price'] === $pledge->stripe_price_id;
            }))
            ->andReturn($subscription);

        $stripe = Mockery::mock(StripeClient::class);
        $stripe->subscriptions = $subscriptions;

        // StripeService must have: public function __construct(?StripeClient $stripe = null)
        $service = new StripeService($stripe);

        $service->createSubscriptionForPledge($pledge, 'pm_123');

        $pledge->refresh();

        $this->assertSame('sub_999', $pledge->stripe_subscription_id);
        $this->assertSame('active', $pledge->status);
        $this->assertNotNull($pledge->current_period_start);
        $this->assertNotNull($pledge->current_period_end);
        $this->assertNotNull($pledge->next_pledge_at);
        $this->assertSame('in_999', $pledge->latest_invoice_id);
        $this->assertSame('pi_999', $pledge->latest_payment_intent_id);

        $this->assertDatabaseHas('transactions', [
            'pledge_id'       => $pledge->id,
            'subscription_id' => 'sub_999',
            'amount_cents'    => 1000,
            'type'            => 'subscription_recurring',
            'status'          => 'succeeded',
            'source'          => 'donation_widget',
        ]);
    }

    public function test_cancel_subscription_at_period_end_updates_pledge_fields(): void
    {
        $pledge = Pledge::forceCreate([
            'user_id'                => null,
            'stripe_subscription_id' => 'sub_cancel',
            'amount_cents'           => 1000,
            'currency'               => 'usd',
            'interval'               => 'month',
            'status'                 => 'active',
            'cancel_at_period_end'   => false,
        ]);

        $subscription = StripeSubscription::constructFrom([
            'id'                   => 'sub_cancel',
            'status'               => 'active',
            'cancel_at_period_end' => true,
            // In reality Stripe *may* omit or null these on update, so we
            // don’t assert on them in this test.
            'current_period_start' => 1_763_424_000,
            'current_period_end'   => 1_766_016_000,
        ], null);

        $subscriptions = Mockery::mock();
        $subscriptions
            ->shouldReceive('update')
            ->once()
            ->with('sub_cancel', ['cancel_at_period_end' => true])
            ->andReturn($subscription);

        $stripe = Mockery::mock(StripeClient::class);
        $stripe->subscriptions = $subscriptions;

        $service = new StripeService($stripe);
        $service->cancelSubscriptionAtPeriodEnd($pledge);

        $pledge->refresh();

        $this->assertSame('active', $pledge->status);
        $this->assertTrue($pledge->cancel_at_period_end);
        // We deliberately DO NOT assert on current_period_* here because your
        // real Stripe webhooks sometimes send them as null and we don’t want
        // a brittle test.
    }

    public function test_update_subscription_amount_creates_new_price_and_updates_pledge(): void
    {
        $pledge = Pledge::forceCreate([
            'user_id'                => null,
            'stripe_subscription_id' => 'sub_update',
            'stripe_price_id'        => 'price_old',
            'amount_cents'           => 1000,
            'currency'               => 'usd',
            'interval'               => 'month',
            'status'                 => 'active',
            'cancel_at_period_end'   => false,
        ]);

        $subscriptionRetrieved = StripeSubscription::constructFrom([
            'id'                   => 'sub_update',
            'status'               => 'active',
            'cancel_at_period_end' => false,
            'current_period_start' => 1_700_000_000,
            'current_period_end'   => 1_702_592_000,
            'items'                => [
                'data' => [
                    [
                        'id'    => 'si_123',
                        'price' => [
                            'product' => 'prod_123',
                        ],
                    ],
                ],
            ],
        ], null);

        $subscriptionUpdated = StripeSubscription::constructFrom([
            'id'                   => 'sub_update',
            'status'               => 'active',
            'cancel_at_period_end' => false,
            'current_period_start' => 1_700_000_000,
            'current_period_end'   => 1_702_592_000,
        ], null);

        $subscriptions = Mockery::mock();
        $subscriptions
            ->shouldReceive('retrieve')
            ->once()
            ->with('sub_update', ['expand' => ['items.data.price']])
            ->andReturn($subscriptionRetrieved);

        $subscriptions
            ->shouldReceive('update')
            ->once()
            ->with('sub_update', Mockery::on(function ($params) {
                return $params['items'][0]['price'] === 'price_new';
            }))
            ->andReturn($subscriptionUpdated);

        $prices = Mockery::mock();
        $prices
            ->shouldReceive('create')
            ->once()
            ->andReturn((object) ['id' => 'price_new']);

        $stripe = Mockery::mock(StripeClient::class);
        $stripe->subscriptions = $subscriptions;
        $stripe->prices        = $prices;

        $service = new StripeService($stripe);
        $service->updateSubscriptionAmount($pledge, 2000);

        $pledge->refresh();

        $this->assertSame(2000, $pledge->amount_cents);
        $this->assertSame('price_new', $pledge->stripe_price_id);
        $this->assertSame('active', $pledge->status);
        $this->assertFalse($pledge->cancel_at_period_end);
        $this->assertNotNull($pledge->current_period_start);
        $this->assertNotNull($pledge->current_period_end);
        $this->assertNotNull($pledge->next_pledge_at);
    }

    public function test_refund_creates_refund_model_from_stripe_response(): void
    {
        $tx = Transaction::factory()->create([
            'charge_id'    => 'ch_123',
            'amount_cents' => 2000,
            'currency'     => 'usd',
        ]);

        // Important: metadata is NULL so StripeService doesn’t call ->toArray()
        $stripeRefund = (object) [
            'id'       => 're_123',
            'charge'   => 'ch_123',
            'amount'   => 2000,
            'currency' => 'usd',
            'status'   => 'succeeded',
            'reason'   => null,
            'metadata' => null,
        ];

        $refunds = Mockery::mock();
        $refunds
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($params) use ($tx) {
                return $params['charge'] === $tx->charge_id
                    && $params['amount'] === $tx->amount_cents
                    && $params['metadata']['transaction_id'] === (string) $tx->id;
            }))
            ->andReturn($stripeRefund);

        $stripe = Mockery::mock(StripeClient::class);
        $stripe->refunds = $refunds;

        $service = new StripeService($stripe);
        $refund  = $service->refund($tx);

        $this->assertInstanceOf(Refund::class, $refund);

        $this->assertDatabaseHas('refunds', [
            'transaction_id'   => $tx->id,
            'stripe_refund_id' => 're_123',
            'charge_id'        => 'ch_123',
            'amount_cents'     => 2000,
            'currency'         => 'usd',
            'status'           => 'succeeded',
        ]);
    }
    
    public function test_get_or_create_customer_reuses_existing_customer_from_transactions(): void
    {
        // Existing transaction with a customer_id + email
        Transaction::factory()->create([
            'user_id'      => null,
            'customer_id'  => 'cus_existing_123',
            'payer_email'  => 'donor@example.test',
            'amount_cents' => 1000,
            'currency'     => 'usd',
        ]);

        // Inject a mocked Stripe client so StripeService doesn't create a real one
        $stripe = Mockery::mock(StripeClient::class);

        // StripeService must be:
        // public function __construct(?StripeClient $stripe = null)
        $service = new StripeService($stripe);

        $customerId = $service->getOrCreateCustomer([
            'email' => 'donor@example.test',
            'name'  => 'Test Donor',
        ]);

        // Because we already had a matching Transaction, it should reuse that customer
        $this->assertSame('cus_existing_123', $customerId);
    }

    public function test_resume_subscription_clears_cancel_at_period_end_and_updates_pledge_fields(): void
    {
        $start = now()->subDays(5);
        $end   = now()->addDays(25);

        $pledge = Pledge::forceCreate([
            'user_id'                => null,
            'stripe_subscription_id' => 'sub_resume',
            'amount_cents'           => 1000,
            'currency'               => 'usd',
            'interval'               => 'month',
            'status'                 => 'active',
            'cancel_at_period_end'   => true,
            'current_period_start'   => $start,
            'current_period_end'     => $end,
            'next_pledge_at'         => $end,
        ]);

        $subscription = \Stripe\Subscription::constructFrom([
            'id'                   => 'sub_resume',
            'status'               => 'active',
            'cancel_at_period_end' => false,
            'current_period_start' => $start->getTimestamp(),
            'current_period_end'   => $end->getTimestamp(),
        ], null);

        $subscriptions = \Mockery::mock();
        $subscriptions
            ->shouldReceive('update')
            ->once()
            ->with('sub_resume', ['cancel_at_period_end' => false])
            ->andReturn($subscription);

        $stripe = \Mockery::mock(\Stripe\StripeClient::class);
        $stripe->subscriptions = $subscriptions;

        $service = new \App\Services\StripeService($stripe);
        $service->resumeSubscription($pledge);

        $pledge->refresh();

        $this->assertSame('active', $pledge->status);
        $this->assertFalse($pledge->cancel_at_period_end);
        $this->assertNotNull($pledge->current_period_start);
        $this->assertNotNull($pledge->current_period_end);
        $this->assertNotNull($pledge->next_pledge_at);
    }
}
