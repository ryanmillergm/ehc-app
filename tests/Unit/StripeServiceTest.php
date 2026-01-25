<?php

namespace Tests\Unit;

use App\Http\Controllers\StripeWebhookController;
use App\Models\Pledge;
use App\Models\Refund;
use App\Models\Transaction;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Stripe\StripeClient;
use Stripe\Subscription as StripeSubscription;
use Tests\TestCase;

class StripeServiceTest extends TestCase
{
    use RefreshDatabase;
    use MockeryPHPUnitIntegration;

    public function test_create_subscription_for_pledge_uses_existing_customer_and_price_and_creates_initial_transaction(): void
    {
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

            // NOTE: no attempt_id here -> StripeService will NOT pass $opts arg
            'attempt_id'         => null,
        ]);

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
            ->withArgs(function (...$args) use ($pledge) {
                $params = $args[0] ?? null;
                $opts   = $args[1] ?? null; // optional

                if (! is_array($params)) {
                    return false;
                }

                $ok =
                    ($params['customer'] ?? null) === $pledge->stripe_customer_id
                    && (($params['items'][0]['price'] ?? null) === $pledge->stripe_price_id)
                    && (($params['default_payment_method'] ?? null) === 'pm_123')
                    && (($params['collection_method'] ?? null) === 'charge_automatically')
                    && isset($params['expand'])
                    && is_array($params['expand'])
                    && isset($params['metadata'])
                    && is_array($params['metadata']);

                // If your service still passes idempotency, allow it (but don’t require it)
                if ($opts !== null) {
                    if (! is_array($opts)) {
                        return false;
                    }

                    if (isset($opts['idempotency_key'])) {
                        $key = $opts['idempotency_key'];

                        if (! is_string($key)) {
                            return false;
                        }

                        // allow hashed suffix (…:HASH)
                        if (! str_starts_with($key, 'subscription:pledge:' . $pledge->id . ':')) {
                            return false;
                        }
                    }
                }

                return $ok;
            })
            ->andReturn($subscription);

        $stripe = Mockery::mock(StripeClient::class);
        $stripe->subscriptions = $subscriptions;

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
            'type'            => 'subscription_initial',
            'status'          => 'pending',
            'source'          => 'donation_widget',
        ]);
    }

    public function test_invoice_paid_updates_existing_pending_widget_transaction_and_does_not_duplicate(): void
    {
        $pledge = Pledge::forceCreate([
            'user_id'                => null,
            'amount_cents'           => 1500,
            'currency'               => 'usd',
            'interval'               => 'month',
            'status'                 => 'incomplete',
            'donor_email'            => 'donor@example.test',
            'donor_name'             => 'Test Donor',
            'stripe_subscription_id' => 'sub_123',
            'stripe_customer_id'     => 'cus_123',
        ]);

        $pendingTx = Transaction::factory()->create([
            'pledge_id'         => $pledge->id,
            'subscription_id'   => 'sub_123',
            'customer_id'       => 'cus_123',
            'payment_intent_id' => null,
            'charge_id'         => null,
            'stripe_invoice_id' => null,
            'amount_cents'      => 1500,
            'currency'          => 'usd',
            'type'              => 'subscription_recurring',
            'status'            => 'pending',
            'source'            => 'donation_widget',
            'paid_at'           => null,
            'metadata'          => [],
            'created_at'        => now(),
        ]);

        $periodStart = 1_700_000_000;
        $periodEnd   = $periodStart + 2_592_000;

        $event = (object) [
            'type' => 'invoice.paid',
            'data' => (object) [
                'object' => (object) [
                    'id'                 => 'in_123',
                    'subscription'       => 'sub_123',
                    'customer'           => 'cus_123',
                    'payment_intent'     => 'pi_456',
                    'amount_paid'        => 1500,
                    'amount_due'         => 1500,
                    'currency'           => 'usd',
                    'charge'             => 'ch_456',
                    'customer_email'     => 'alt@example.test',
                    'hosted_invoice_url' => 'https://example.test/invoices/in_123',
                    'lines'              => (object) [
                        'data' => [
                            (object) [
                                'subscription' => 'sub_123',
                                'period' => (object) [
                                    'start' => $periodStart,
                                    'end'   => $periodEnd,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        (new StripeWebhookController())->handleEvent($event);

        $this->assertSame(
            1,
            Transaction::where('pledge_id', $pledge->id)
                ->where('type', 'subscription_recurring')
                ->count()
        );

        $pendingTx->refresh();

        $this->assertSame('succeeded', $pendingTx->status);
        $this->assertSame('pi_456', $pendingTx->payment_intent_id);
        $this->assertSame('ch_456', $pendingTx->charge_id);
        $this->assertSame('donation_widget', $pendingTx->source);
        $this->assertNotNull($pendingTx->paid_at);

        // If your handler sets invoice id for invoice events (recommended), assert it:
        $this->assertSame('in_123', $pendingTx->stripe_invoice_id);

        $this->assertSame(
            $pendingTx->id,
            Transaction::where('payment_intent_id', 'pi_456')->value('id')
        );
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
                return ($params['items'][0]['price'] ?? null) === 'price_new';
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

            // NOTE: no attempt_id here -> StripeService will NOT pass $opts arg
            'attempt_id'   => null,
        ]);

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
            ->withArgs(function (...$args) use ($tx) {
                $params = $args[0] ?? null;
                $opts   = $args[1] ?? null; // optional

                if (! is_array($params)) {
                    return false;
                }

                $ok =
                    ($params['charge'] ?? null) === $tx->charge_id
                    && ((int) ($params['amount'] ?? -1)) === (int) $tx->amount_cents
                    && isset($params['metadata']['transaction_id'])
                    && ((string) $params['metadata']['transaction_id']) === (string) $tx->id;

                // Allow (but don’t require) idempotency options
                if ($opts !== null) {
                    if (! is_array($opts)) {
                        return false;
                    }

                    if (isset($opts['idempotency_key'])) {
                        $key = $opts['idempotency_key'];

                        if (! is_string($key)) {
                            return false;
                        }

                        if (! str_starts_with($key, 'refund:tx:' . $tx->id . ':')) {
                            return false;
                        }
                    }
                }

                return $ok;
            })
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
        Transaction::factory()->create([
            'user_id'      => null,
            'customer_id'  => 'cus_existing_123',
            'payer_email'  => 'donor@example.test',
            'amount_cents' => 1000,
            'currency'     => 'usd',
        ]);

        $stripe = Mockery::mock(StripeClient::class);

        $service = new StripeService($stripe);

        $customerId = $service->getOrCreateCustomer([
            'email' => 'donor@example.test',
            'name'  => 'Test Donor',
        ]);

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

        $subscription = StripeSubscription::constructFrom([
            'id'                   => 'sub_resume',
            'status'               => 'active',
            'cancel_at_period_end' => false,
            'current_period_start' => $start->getTimestamp(),
            'current_period_end'   => $end->getTimestamp(),
        ], null);

        $subscriptions = Mockery::mock();
        $subscriptions
            ->shouldReceive('update')
            ->once()
            ->with('sub_resume', ['cancel_at_period_end' => false])
            ->andReturn($subscription);

        $stripe = Mockery::mock(StripeClient::class);
        $stripe->subscriptions = $subscriptions;

        $service = new StripeService($stripe);
        $service->resumeSubscription($pledge);

        $pledge->refresh();

        $this->assertSame('active', $pledge->status);
        $this->assertFalse($pledge->cancel_at_period_end);
        $this->assertNotNull($pledge->current_period_start);
        $this->assertNotNull($pledge->current_period_end);
        $this->assertNotNull($pledge->next_pledge_at);
    }
}
