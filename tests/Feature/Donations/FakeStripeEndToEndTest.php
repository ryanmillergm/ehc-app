<?php

namespace Tests\Feature\Donations;

use App\Http\Controllers\StripeWebhookController;
use App\Models\Pledge;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Stripe\Charge;
use Stripe\Customer;
use Stripe\PaymentIntent;
use Stripe\PaymentMethod;
use Stripe\Price;
use Stripe\SetupIntent;
use Stripe\StripeClient;
use Stripe\Subscription;
use Tests\TestCase;

class FakeStripeEndToEndTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.stripe.recurring_product_id', 'prod_FAKE_RECURRING');

        app()->instance(StripeClient::class, new FakeStripeClient());
    }

    #[Test]
    public function monthly_flow_start_complete_then_webhooks_leave_database_consistent_and_never_duplicate_tx_rows_even_out_of_order(): void
    {
        $this->withoutExceptionHandling();

        Carbon::setTestNow(Carbon::parse('2025-01-01 00:00:00'));

        // ------------------------------------------------------------
        // 1) START (monthly)
        // ------------------------------------------------------------
        $start = $this->postJson(route('donations.start'), [
            'amount'     => 3.01,
            'frequency'  => 'monthly',
        ])->assertOk()->json();

        $this->assertSame('subscription', $start['mode']);

        $attemptId = $start['attemptId'];
        
        $this->assertNotEmpty($attemptId);
        $this->assertNotEmpty($start['clientSecret']);
        $this->assertNotEmpty($start['pledgeId']);

        $pledge = Pledge::findOrFail($start['pledgeId']);
        $this->assertSame($attemptId, $pledge->attempt_id);
        $this->assertSame(301, (int) $pledge->amount_cents);

        // ------------------------------------------------------------
        // 2) COMPLETE (subscription) -> creates pledge tx placeholder + creates subscription via StripeService
        // ------------------------------------------------------------
        $completeResponse = $this->postJson(route('donations.complete'), [
            'attempt_id'        => $attemptId,
            'mode'              => 'subscription',
            'pledge_id'         => $pledge->id,
            'payment_method_id' => 'pm_FAKE_1',
            'donor_first_name'  => 'Test',
            'donor_last_name'   => 'Donor',
            'donor_email'       => 'test@example.com',
        ]);

        $completeResponse
            ->assertOk()
            ->assertSessionHas('pledge_thankyou_id', $pledge->id);

        $complete = $completeResponse->json();
        $this->assertNotEmpty($complete['redirect'] ?? null);

        $pledge->refresh();

        // Synced from StripeService::createSubscriptionForPledge (FakeStripeClient subscription object)
        $this->assertSame('cus_FAKE_1', $pledge->stripe_customer_id);
        $this->assertSame('price_FAKE_301', $pledge->stripe_price_id);
        $this->assertSame('sub_FAKE_1', $pledge->stripe_subscription_id);
        $this->assertSame('active', $pledge->status);
        $this->assertSame('in_WEBHOOK_1', $pledge->latest_invoice_id);
        $this->assertSame('pi_FAKE_INITIAL', $pledge->latest_payment_intent_id);

        // Canonical initial tx is the one tied to the initial PI.
        $initialTx = Transaction::query()
            ->where('payment_intent_id', 'pi_FAKE_INITIAL')
            ->firstOrFail();

        $this->assertSame($pledge->id, $initialTx->pledge_id);
        $this->assertSame($attemptId, $initialTx->attempt_id);
        $this->assertSame('subscription_initial', $initialTx->type);
        $this->assertSame('pending', $initialTx->status);
        $this->assertSame('ch_FAKE_INITIAL', $initialTx->charge_id);
        $this->assertNull($initialTx->paid_at);

        // No “dangling placeholder” that never got enriched
        $this->assertSame(0, Transaction::query()
            ->where('pledge_id', $pledge->id)
            ->whereIn('type', ['subscription_initial', 'subscription_recurring'])
            ->whereNull('payment_intent_id')
            ->whereNull('charge_id')
            ->count()
        );

        $webhook = app(StripeWebhookController::class);

        // ------------------------------------------------------------
        // 3) OUT-OF-ORDER + RETRIES (initial): charge.succeeded + PI.succeeded BEFORE invoice.paid
        // ------------------------------------------------------------
        $chargeInitial = (object) [
            'id' => 'ch_FAKE_INITIAL',
            'customer' => 'cus_FAKE_1',
            'invoice' => 'in_WEBHOOK_1',
            'payment_intent' => 'pi_FAKE_INITIAL',
            'payment_method' => 'pm_FAKE_1',
            'amount' => 301,
            'currency' => 'usd',
            'billing_details' => (object) [
                'email' => 'test@example.com',
                'name'  => 'Test Donor',
            ],
            'payment_method_details' => [
                'card' => [
                    'brand' => 'visa',
                    'last4' => '4242',
                    'country' => 'US',
                    'funding' => 'credit',
                    'exp_month' => 12,
                    'exp_year' => 2030,
                ],
            ],
        ];

        $piInitial = (object) [
            'id' => 'pi_FAKE_INITIAL',
            'customer' => 'cus_FAKE_1',
            'payment_method' => 'pm_FAKE_1',
            'latest_charge' => 'ch_FAKE_INITIAL',
            'invoice' => 'in_WEBHOOK_1',
        ];

        // Bad order + retries
        $webhook->handleEvent((object) ['type' => 'charge.succeeded', 'data' => (object) ['object' => $chargeInitial]]);
        $webhook->handleEvent((object) ['type' => 'payment_intent.succeeded', 'data' => (object) ['object' => $piInitial]]);
        $webhook->handleEvent((object) ['type' => 'charge.succeeded', 'data' => (object) ['object' => $chargeInitial]]);
        $webhook->handleEvent((object) ['type' => 'payment_intent.succeeded', 'data' => (object) ['object' => $piInitial]]);

        // Still exactly one tx for this PI/charge.
        $this->assertSame(1, Transaction::query()->where('payment_intent_id', 'pi_FAKE_INITIAL')->count());
        $this->assertSame(1, Transaction::query()->where('charge_id', 'ch_FAKE_INITIAL')->count());

        // ------------------------------------------------------------
        // 4) WEBHOOK: invoice.paid (initial) — twice (idempotency)
        // ------------------------------------------------------------
        $invoice1 = (object) [
            'id'                 => 'in_WEBHOOK_1',
            'customer'           => 'cus_FAKE_1',
            'subscription'       => 'sub_FAKE_1',
            'payment_intent'     => 'pi_FAKE_INITIAL',
            'charge'             => 'ch_FAKE_INITIAL',
            'amount_paid'        => 301,
            'currency'           => 'usd',
            'billing_reason'     => 'subscription_create',
            'hosted_invoice_url' => 'https://invoice.test/in_WEBHOOK_1',
            'customer_email'     => 'test@example.com',
            'customer_name'      => 'Test Donor',
            'default_payment_method' => 'pm_FAKE_1',
            'status_transitions' => (object) ['paid_at' => 1700000100],
            'lines' => (object) [
                'data' => [
                    (object) [
                        'period' => (object) ['start' => 1700000000, 'end' => 1702592000],
                    ],
                ],
            ],
        ];

        $webhook->handleEvent((object) ['type' => 'invoice.paid', 'data' => (object) ['object' => $invoice1]]);
        $webhook->handleEvent((object) ['type' => 'invoice.paid', 'data' => (object) ['object' => $invoice1]]); // retry

        $pledge->refresh();
        $this->assertSame('active', $pledge->status);
        $this->assertSame(1700000000, $pledge->current_period_start?->timestamp);
        $this->assertSame(1702592000, $pledge->current_period_end?->timestamp);
        $this->assertSame(1700000100, $pledge->last_pledge_at?->timestamp);
        $this->assertSame(1702592000, $pledge->next_pledge_at?->timestamp);
        $this->assertSame('in_WEBHOOK_1', $pledge->latest_invoice_id);
        $this->assertSame('pi_FAKE_INITIAL', $pledge->latest_payment_intent_id);

        $initialTx->refresh();
        $this->assertSame('subscription_initial', $initialTx->type);
        $this->assertSame('succeeded', $initialTx->status);
        $this->assertNotNull($initialTx->paid_at);

        // No duplicate tx for invoice1 (idempotent)
        $this->assertSame(1, Transaction::query()
            ->where('pledge_id', $pledge->id)
            ->where('metadata->stripe_invoice_id', 'in_WEBHOOK_1')
            ->count()
        );

        // Still exactly one tx for this PI/charge after invoice retries
        $this->assertSame(1, Transaction::query()->where('payment_intent_id', 'pi_FAKE_INITIAL')->count());
        $this->assertSame(1, Transaction::query()->where('charge_id', 'ch_FAKE_INITIAL')->count());

        // ------------------------------------------------------------
        // 5) WEBHOOK: invoice.paid (recurring) — twice + out-of-order retries
        // ------------------------------------------------------------
        $invoice2 = (object) [
            'id'                 => 'in_WEBHOOK_2',
            'customer'           => 'cus_FAKE_1',
            'subscription'       => 'sub_FAKE_1',
            'payment_intent'     => 'pi_FAKE_RECUR_1',
            'charge'             => 'ch_FAKE_RECUR_1',
            'amount_paid'        => 301,
            'currency'           => 'usd',
            'billing_reason'     => 'subscription_cycle',
            'hosted_invoice_url' => 'https://invoice.test/in_WEBHOOK_2',
            'customer_email'     => 'test@example.com',
            'customer_name'      => 'Test Donor',
            'default_payment_method' => 'pm_FAKE_1',
            'status_transitions' => (object) ['paid_at' => 1702592100],
            'lines' => (object) [
                'data' => [
                    (object) [
                        'period' => (object) ['start' => 1702592000, 'end' => 1705184000],
                    ],
                ],
            ],
        ];

        $chargeRecurring = (object) [
            'id' => 'ch_FAKE_RECUR_1',
            'customer' => 'cus_FAKE_1',
            'invoice' => 'in_WEBHOOK_2',
            'payment_intent' => 'pi_FAKE_RECUR_1',
            'payment_method' => 'pm_FAKE_1',
            'amount' => 301,
            'currency' => 'usd',
            'billing_details' => (object) [
                'email' => 'test@example.com',
                'name'  => 'Test Donor',
            ],
            'payment_method_details' => [
                'card' => [
                    'brand' => 'visa',
                    'last4' => '4242',
                    'country' => 'US',
                    'funding' => 'credit',
                    'exp_month' => 12,
                    'exp_year' => 2030,
                ],
            ],
        ];

        $piRecurring = (object) [
            'id' => 'pi_FAKE_RECUR_1',
            'customer' => 'cus_FAKE_1',
            'payment_method' => 'pm_FAKE_1',
            'latest_charge' => 'ch_FAKE_RECUR_1',
            'invoice' => 'in_WEBHOOK_2',
        ];

        // “Bad order” again
        $webhook->handleEvent((object) ['type' => 'charge.succeeded', 'data' => (object) ['object' => $chargeRecurring]]);
        $webhook->handleEvent((object) ['type' => 'invoice.paid', 'data' => (object) ['object' => $invoice2]]);
        $webhook->handleEvent((object) ['type' => 'payment_intent.succeeded', 'data' => (object) ['object' => $piRecurring]]);

        // Retries
        $webhook->handleEvent((object) ['type' => 'invoice.paid', 'data' => (object) ['object' => $invoice2]]);
        $webhook->handleEvent((object) ['type' => 'charge.succeeded', 'data' => (object) ['object' => $chargeRecurring]]);
        $webhook->handleEvent((object) ['type' => 'payment_intent.succeeded', 'data' => (object) ['object' => $piRecurring]]);

        // Must have created exactly one recurring tx for invoice2
        $this->assertSame(1, Transaction::query()
            ->where('pledge_id', $pledge->id)
            ->where('metadata->stripe_invoice_id', 'in_WEBHOOK_2')
            ->count()
        );

        // Exactly two subscription tx rows total: initial + recurring (never 3)
        $this->assertSame(2, Transaction::query()
            ->where('pledge_id', $pledge->id)
            ->whereIn('type', ['subscription_initial', 'subscription_recurring'])
            ->count()
        );

        $recurringTx = Transaction::query()
            ->where('payment_intent_id', 'pi_FAKE_RECUR_1')
            ->firstOrFail();

        $this->assertSame('subscription_recurring', $recurringTx->type);
        $this->assertSame('succeeded', $recurringTx->status);
        $this->assertSame('ch_FAKE_RECUR_1', $recurringTx->charge_id);
        $this->assertNotNull($recurringTx->paid_at);

        // Still unique by PI/charge (no dupes)
        $this->assertSame(1, Transaction::query()->where('payment_intent_id', 'pi_FAKE_RECUR_1')->count());
        $this->assertSame(1, Transaction::query()->where('charge_id', 'ch_FAKE_RECUR_1')->count());

        // Initial tx remains intact
        $initialTx->refresh();
        $this->assertSame('pi_FAKE_INITIAL', $initialTx->payment_intent_id);
        $this->assertSame('subscription_initial', $initialTx->type);
    }
}

/**
 * Fake Stripe client for tests.
 * Minimal surface area needed for your StripeService methods.
 */
class FakeStripeClient extends StripeClient
{
    public $customers;
    public $prices;
    public $subscriptions;
    public $setupIntents;
    public $paymentIntents;
    public $charges;
    public $paymentMethods;

    public function __construct()
    {
        // Do NOT call parent ctor.

        $this->customers = new class {
            public function create(array $params, array $opts = [])
            {
                return Customer::constructFrom(['id' => 'cus_FAKE_1']);
            }

            public function update(string $id, array $params = [], array $opts = [])
            {
                return Customer::constructFrom(['id' => $id]);
            }
        };

        $this->prices = new class {
            public function create(array $params, array $opts = [])
            {
                $amt = (int) ($params['unit_amount'] ?? 0);
                return Price::constructFrom(['id' => 'price_FAKE_' . $amt]);
            }
        };

        $this->setupIntents = new class {
            public function create(array $params, array $opts = [])
            {
                return SetupIntent::constructFrom([
                    'id'            => 'seti_FAKE_1',
                    'client_secret' => 'seti_secret_FAKE_1',
                    'customer'      => $params['customer'] ?? 'cus_FAKE_1',
                    'status'        => 'requires_payment_method',
                ]);
            }

            public function retrieve(string $id, array $params = [])
            {
                return SetupIntent::constructFrom([
                    'id'            => $id,
                    'client_secret' => 'seti_secret_' . $id,
                    'status'        => 'succeeded',
                    'payment_method'=> 'pm_FAKE_1',
                ]);
            }
        };

        $this->subscriptions = new class {
            public function create(array $params, array $opts = [])
            {
                return Subscription::constructFrom([
                    'id' => 'sub_FAKE_1',
                    'status' => 'active',
                    'current_period_start' => 1700000000,
                    'current_period_end'   => 1702592000,
                    'latest_invoice' => [
                        'id' => 'in_WEBHOOK_1',
                        'amount_due' => 301,
                        'currency' => 'usd',
                        'paid' => false,
                        'hosted_invoice_url' => 'https://invoice.test/in_WEBHOOK_1',
                        'payment_intent' => [
                            'id' => 'pi_FAKE_INITIAL',
                            'latest_charge' => 'ch_FAKE_INITIAL',
                        ],
                    ],
                ]);
            }

            public function retrieve(string $id, array $params = [])
            {
                return Subscription::constructFrom([
                    'id' => $id,
                    'status' => 'active',
                    'current_period_start' => 1700000000,
                    'current_period_end'   => 1702592000,
                    'latest_invoice' => [
                        'id' => 'in_WEBHOOK_1',
                        'amount_due' => 301,
                        'currency' => 'usd',
                        'paid' => false,
                        'hosted_invoice_url' => 'https://invoice.test/in_WEBHOOK_1',
                        'payment_intent' => [
                            'id' => 'pi_FAKE_INITIAL',
                            'latest_charge' => 'ch_FAKE_INITIAL',
                        ],
                    ],
                ]);
            }

            public function update(string $id, array $params = [], array $opts = [])
            {
                return $this->retrieve($id);
            }
        };

        $this->paymentIntents = new class {
            public function create(array $params, array $opts = [])
            {
                return PaymentIntent::constructFrom([
                    'id'            => 'pi_FAKE_ONE_TIME',
                    'client_secret' => 'pi_secret_FAKE_ONE_TIME',
                    'status'        => 'requires_payment_method',
                    'customer'      => $params['customer'] ?? null,
                ]);
            }

            public function retrieve(string $id, array $params = [])
            {
                $latestCharge = match ($id) {
                    'pi_FAKE_INITIAL'   => 'ch_FAKE_INITIAL',
                    'pi_FAKE_RECUR_1'   => 'ch_FAKE_RECUR_1',
                    default             => 'ch_FAKE_ONE_TIME',
                };

                $invoice = match ($id) {
                    'pi_FAKE_INITIAL'   => 'in_WEBHOOK_1',
                    'pi_FAKE_RECUR_1'   => 'in_WEBHOOK_2',
                    default             => null,
                };

                return PaymentIntent::constructFrom([
                    'id'             => $id,
                    'status'         => 'succeeded',
                    'customer'       => 'cus_FAKE_1',
                    'payment_method' => 'pm_FAKE_1',
                    'latest_charge'  => $latestCharge,
                    'invoice'        => $invoice,
                ]);
            }
        };

        $this->charges = new class {
            public function retrieve(string $id, array $params = [])
            {
                return Charge::constructFrom([
                    'id' => $id,
                    'receipt_url' => 'https://receipt.test/' . $id,
                    'billing_details' => [
                        'email' => 'test@example.com',
                        'name'  => 'Test Donor',
                    ],
                    'payment_method_details' => [
                        'card' => [
                            'brand'     => 'visa',
                            'last4'     => '4242',
                            'country'   => 'US',
                            'funding'   => 'credit',
                            'exp_month' => 12,
                            'exp_year'  => 2030,
                        ],
                    ],
                ]);
            }
        };


        $this->paymentMethods = new class {
            public function retrieve(string $id, array $params = [])
            {
                return PaymentMethod::constructFrom([
                    'id' => $id,
                    'billing_details' => [
                        'email' => 'test@example.com',
                        'name'  => 'Test Donor',
                    ],
                ]);
            }
        };
    }
}
