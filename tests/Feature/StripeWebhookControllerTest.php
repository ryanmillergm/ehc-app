<?php

namespace Tests\Feature;

use App\Http\Controllers\StripeWebhookController;
use App\Models\Pledge;
use App\Models\Refund;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StripeWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_intent_succeeded_sets_status_and_paid_at_when_transaction_exists(): void
    {
        $tx = Transaction::factory()->create([
            'payment_intent_id' => 'pi_123',
            'status'            => 'pending',
            'paid_at'           => null,
        ]);

        $event = (object) [
            'type' => 'payment_intent.succeeded',
            'data' => (object) [
                'object' => (object) [
                    'id' => 'pi_123',
                ],
            ],
        ];

        $controller = new StripeWebhookController();
        $controller->handleEvent($event);

        $tx->refresh();

        $this->assertSame('succeeded', $tx->status);
        $this->assertNotNull($tx->paid_at);
    }

    public function test_nvoice_paid_creates_subscription_recurring_transaction_and_updates_pledge(): void
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
            'metadata'               => [],
        ]);

        // Make timestamps deterministic
        $periodStart = 1_700_000_000;
        $periodEnd   = $periodStart + 2_592_000;

        // Stripe "paid at" should represent when it was actually charged
        $paidAtTs = $periodStart + 120; // 2 minutes after period start (arbitrary but deterministic)

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

                    // Ensure controller picks subscription_recurring, not subscription_initial
                    'billing_reason'     => 'subscription_cycle',

                    // paid timestamp for last_pledge_at
                    'status_transitions' => (object) [
                        'paid_at' => $paidAtTs,
                    ],

                    'lines' => (object) [
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

        $pledge->refresh();

        $expectedStart  = Carbon::createFromTimestamp($periodStart);
        $expectedEnd    = Carbon::createFromTimestamp($periodEnd);
        $expectedPaidAt = Carbon::createFromTimestamp($paidAtTs);

        $this->assertSame('active', $pledge->status);

        $this->assertEquals($expectedStart->timestamp, $pledge->current_period_start->timestamp);
        $this->assertEquals($expectedEnd->timestamp, $pledge->current_period_end->timestamp);

        // charged time
        $this->assertEquals($expectedPaidAt->timestamp, $pledge->last_pledge_at->timestamp);

        // next time it will charge (renewal boundary)
        $this->assertEquals($expectedEnd->timestamp, $pledge->next_pledge_at->timestamp);

        // sanity: these should not be the same
        $this->assertTrue($pledge->last_pledge_at->lt($pledge->next_pledge_at));

        // And the transaction has the hosted invoice URL as the receipt_url
        $tx = Transaction::where('payment_intent_id', 'pi_456')->firstOrFail();

        $this->assertSame($pledge->id, $tx->pledge_id);
        $this->assertSame('subscription_recurring', $tx->type);
        $this->assertSame('succeeded', $tx->status);
        $this->assertSame('ch_456', $tx->charge_id);
        $this->assertSame('cus_123', $tx->customer_id);
        $this->assertSame('https://example.test/invoices/in_123', $tx->receipt_url);

        // Optional: make sure invoice metadata is present if your controller merges it
        $this->assertIsArray($tx->metadata);
        $this->assertSame('in_123', $tx->metadata['stripe_invoice_id'] ?? null);
        $this->assertSame('sub_123', $tx->metadata['stripe_subscription_id'] ?? null);
    }

    public function test_invoice_paid_uses_customer_fallback_when_subscription_missing(): void
    {
        $pledge = Pledge::forceCreate([
            'user_id'                => null,
            'amount_cents'           => 2000,
            'currency'               => 'usd',
            'interval'               => 'month',
            'status'                 => 'incomplete',
            'donor_email'            => 'donor@example.test',
            'donor_name'             => 'Test Donor',
            'stripe_subscription_id' => 'sub_fallback',
            'stripe_customer_id'     => 'cus_fallback',
        ]);

        $periodStart = 1_800_000_000;
        $periodEnd   = $periodStart + 2_592_000;

        $event = (object) [
            'type' => 'invoice.payment_succeeded',
            'data' => (object) [
                'object' => (object) [
                    'id'                 => 'in_fallback',
                    'subscription'       => null, // <- missing; forces fallback
                    'customer'           => 'cus_fallback',
                    'payment_intent'     => 'pi_fallback',
                    'amount_paid'        => 2000,
                    'amount_due'         => 2000,
                    'currency'           => 'usd',
                    'charge'             => 'ch_fallback',
                    'customer_email'     => 'donor@example.test',
                    'hosted_invoice_url' => 'https://example.test/invoices/in_fallback',
                    'lines'              => (object) [
                        'data' => [
                            (object) [
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

        $controller = new StripeWebhookController();
        $controller->handleEvent($event);

        $pledge->refresh();

        // Fallback resolved to pledge via customer and still updated everything
        $this->assertSame('active', $pledge->status);
        $this->assertSame('in_fallback', $pledge->latest_invoice_id);
        $this->assertSame('pi_fallback', $pledge->latest_payment_intent_id);
        $this->assertEquals($periodEnd, $pledge->current_period_end->timestamp);

        $this->assertDatabaseHas('transactions', [
            'pledge_id'       => $pledge->id,
            'subscription_id' => 'sub_fallback',
            'amount_cents'    => 2000,
            'type'            => 'subscription_recurring',
        ]);
    }

    public function test_invoice_payment_failed_marks_pledge_past_due(): void
    {
        $pledge = Pledge::forceCreate([
            'user_id'                => null,
            'amount_cents'           => 1500,
            'currency'               => 'usd',
            'interval'               => 'month',
            'status'                 => 'active',
            'donor_email'            => 'donor@example.test',
            'donor_name'             => 'Test Donor',
            'stripe_subscription_id' => 'sub_999',
        ]);

        $event = (object) [
            'type' => 'invoice.payment_failed',
            'data' => (object) [
                'object' => (object) [
                    'id'           => 'in_failed',
                    'subscription' => 'sub_999',
                ],
            ],
        ];

        $controller = new StripeWebhookController();
        $controller->handleEvent($event);

        $this->assertDatabaseHas('pledges', [
            'id'     => $pledge->id,
            'status' => 'past_due',
        ]);
    }

    public function test_subscription_updated_sets_periods_and_cancel_flag_when_timestamps_present(): void
    {
        $pledge = Pledge::forceCreate([
            'user_id'                => null,
            'amount_cents'           => 1000,
            'currency'               => 'usd',
            'interval'               => 'month',
            'status'                 => 'incomplete',
            'donor_email'            => 'donor@example.test',
            'donor_name'             => 'Test Donor',
            'stripe_subscription_id' => 'sub_777',
            'current_period_start'   => null,
            'current_period_end'     => null,
            'next_pledge_at'         => null,
        ]);

        $startTs = 1_763_424_000;
        $endTs   = 1_766_016_000;

        $event = (object) [
            'type' => 'customer.subscription.updated',
            'data' => (object) [
                'object' => (object) [
                    'id'                   => 'sub_777',
                    'status'               => 'active',
                    'cancel_at_period_end' => true,
                    'current_period_start' => $startTs,
                    'current_period_end'   => $endTs,
                ],
            ],
        ];

        $controller = new StripeWebhookController();
        $controller->handleEvent($event);

        $pledge->refresh();

        $this->assertSame('active', $pledge->status);
        $this->assertTrue($pledge->cancel_at_period_end);
        $this->assertEquals($startTs, $pledge->current_period_start->timestamp);
        $this->assertEquals($endTs, $pledge->current_period_end->timestamp);
        $this->assertEquals($endTs, $pledge->next_pledge_at->timestamp);
    }

    public function test_subscription_updated_with_null_periods_preserves_existing_dates(): void
    {
        $existingStart = now()->subDays(5);
        $existingEnd   = now()->addDays(25);

        $pledge = Pledge::forceCreate([
            'user_id'                => null,
            'amount_cents'           => 1000,
            'currency'               => 'usd',
            'interval'               => 'month',
            'status'                 => 'active',
            'donor_email'            => 'donor@example.test',
            'donor_name'             => 'Test Donor',
            'stripe_subscription_id' => 'sub_888',
            'current_period_start'   => $existingStart,
            'current_period_end'     => $existingEnd,
            'next_pledge_at'         => $existingEnd,
            'cancel_at_period_end'   => false,
        ]);

        $event = (object) [
            'type' => 'customer.subscription.updated',
            'data' => (object) [
                'object' => (object) [
                    'id'                   => 'sub_888',
                    'status'               => 'active',
                    'cancel_at_period_end' => true,
                    'current_period_start' => null,
                    'current_period_end'   => null,
                ],
            ],
        ];

        $controller = new StripeWebhookController();
        $controller->handleEvent($event);

        $pledge->refresh();

        $this->assertSame('active', $pledge->status);
        $this->assertTrue($pledge->cancel_at_period_end);

        $this->assertEquals($existingStart->timestamp, $pledge->current_period_start->timestamp);
        $this->assertEquals($existingEnd->timestamp, $pledge->current_period_end->timestamp);
        $this->assertEquals($existingEnd->timestamp, $pledge->next_pledge_at->timestamp);
    }

    public function test_charge_succeeded_enriches_existing_transaction_with_charge_details(): void
    {
        $tx = Transaction::factory()->create([
            'payment_intent_id' => 'pi_123',
            'status'            => 'succeeded',
            'charge_id'         => null,
            'receipt_url'       => null,
            'payer_email'       => null,
            'payer_name'        => null,
            'metadata'          => [],
        ]);

        $event = (object) [
            'type' => 'charge.succeeded',
            'data' => (object) [
                'object' => (object) [
                    'id'              => 'ch_123',
                    'payment_intent'  => 'pi_123',
                    'receipt_url'     => 'https://example.test/stripe-receipt',
                    'billing_details' => (object) [
                        'email' => 'donor@example.test',
                        'name'  => 'Test Donor',
                    ],
                    'payment_method_details' => (object) [
                        'card' => (object) [
                            'brand'   => 'visa',
                            'last4'   => '4242',
                            'country' => 'US',
                            'funding' => 'credit',
                        ],
                    ],
                ],
            ],
        ];

        (new StripeWebhookController())->handleEvent($event);

        $tx->refresh();

        $this->assertSame('ch_123', $tx->charge_id);
        $this->assertSame('https://example.test/stripe-receipt', $tx->receipt_url);
        $this->assertSame('donor@example.test', $tx->payer_email);
        $this->assertSame('Test Donor', $tx->payer_name);

        $this->assertArrayHasKey('card_brand', $tx->metadata);
        $this->assertSame('visa', $tx->metadata['card_brand']);
    }

    public function test_charge_succeeded_backfills_payment_method_id_and_customer_id_when_missing(): void
    {
        $tx = Transaction::factory()->create([
            'payment_intent_id' => 'pi_123',
            'status'            => 'succeeded',
            'charge_id'         => null,
            'receipt_url'       => null,
            'payer_email'       => null,
            'payer_name'        => null,
            'payment_method_id' => null,
            'customer_id'       => null,
            'metadata'          => [],
        ]);

        $event = (object) [
            'type' => 'charge.succeeded',
            'data' => (object) [
                'object' => (object) [
                    'id'              => 'ch_123',
                    'payment_intent'  => 'pi_123',
                    'payment_method'  => 'pm_abc123',
                    'customer'        => 'cus_abc123',
                    'receipt_url'     => 'https://example.test/stripe-receipt',
                    'billing_details' => (object) [
                        'email' => 'donor@example.test',
                        'name'  => 'Test Donor',
                    ],
                    'payment_method_details' => (object) [
                        'card' => (object) [
                            'brand'   => 'visa',
                            'last4'   => '4242',
                            'country' => 'US',
                            'funding' => 'credit',
                        ],
                    ],
                ],
            ],
        ];

        (new StripeWebhookController())->handleEvent($event);

        $tx->refresh();

        $this->assertSame('ch_123', $tx->charge_id);
        $this->assertSame('https://example.test/stripe-receipt', $tx->receipt_url);
        $this->assertSame('donor@example.test', $tx->payer_email);
        $this->assertSame('Test Donor', $tx->payer_name);
        $this->assertSame('pm_abc123', $tx->payment_method_id);
        $this->assertSame('cus_abc123', $tx->customer_id);
    }

    public function test_charge_refunded_marks_transaction_refunded_and_creates_refund_row(): void
    {
        $tx = Transaction::factory()->create([
            'charge_id'    => 'ch_123',
            'status'       => 'succeeded',
            'amount_cents' => 2000,
            'currency'     => 'usd',
        ]);

        $event = (object) [
            'type' => 'charge.refunded',
            'data' => (object) [
                'object' => (object) [
                    'id'      => 'ch_123',
                    'refunds' => (object) [
                        'data' => [
                            (object) [
                                'id'       => 're_123',
                                'amount'   => 1500,
                                'currency' => 'usd',
                                'status'   => 'succeeded',
                                'reason'   => null,
                                'metadata' => (object) ['foo' => 'bar'],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        (new StripeWebhookController())->handleEvent($event);

        $this->assertDatabaseHas('transactions', [
            'id'     => $tx->id,
            'status' => 'refunded',
        ]);

        $this->assertDatabaseHas('refunds', [
            'transaction_id'   => $tx->id,
            'stripe_refund_id' => 're_123',
            'charge_id'        => 'ch_123',
            'amount_cents'     => 1500,
            'currency'         => 'usd',
            'status'           => 'succeeded',
        ]);

        $this->assertSame(1, Refund::count());
    }

    public function test_invoice_paid_uses_charges_data_fallback_when_charge_is_null(): void
    {
        $pledge = Pledge::forceCreate([
            'user_id'                => null,
            'amount_cents'           => 102,
            'currency'               => 'usd',
            'interval'               => 'month',
            'status'                 => 'incomplete',
            'donor_email'            => 'donor@example.test',
            'donor_name'             => 'Test Donor',
            'stripe_subscription_id' => 'sub_charge_fallback',
            'stripe_customer_id'     => 'cus_charge_fallback',
        ]);

        $periodStart = 1_900_000_000;
        $periodEnd   = $periodStart + 2_592_000;

        $event = (object) [
            'type' => 'invoice.paid',
            'data' => (object) [
                'object' => (object) [
                    'id'             => 'in_charge_fallback',
                    'subscription'   => 'sub_charge_fallback',
                    'customer'       => 'cus_charge_fallback',
                    'payment_intent' => 'pi_charge_fallback',
                    'amount_paid'    => 102,
                    'amount_due'     => 102,
                    'currency'       => 'usd',

                    'charge'  => null,
                    'charges' => (object) [
                        'data' => [
                            (object) [
                                'id' => 'ch_from_charges',
                            ],
                        ],
                    ],

                    'customer_email'     => 'donor@example.test',
                    'hosted_invoice_url' => 'https://example.test/invoices/in_charge_fallback',
                    'lines'              => (object) [
                        'data' => [
                            (object) [
                                'subscription' => 'sub_charge_fallback',
                                'period'       => (object) [
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

        $this->assertDatabaseHas('transactions', [
            'pledge_id'         => $pledge->id,
            'payment_intent_id' => 'pi_charge_fallback',
            'subscription_id'   => 'sub_charge_fallback',
            'charge_id'         => 'ch_from_charges',
            'type'              => 'subscription_recurring',
            'status'            => 'succeeded',
        ]);
    }

    public function test_charge_succeeded_does_not_clobber_existing_payment_method_or_customer_id(): void
    {
        $tx = Transaction::factory()->create([
            'payment_intent_id' => 'pi_keep',
            'status'            => 'succeeded',
            'payment_method_id' => 'pm_old',
            'customer_id'       => 'cus_old',
            'charge_id'         => null,
            'receipt_url'       => null,
            'metadata'          => [],
        ]);

        $event = (object) [
            'type' => 'charge.succeeded',
            'data' => (object) [
                'object' => (object) [
                    'id'             => 'ch_new',
                    'payment_intent' => 'pi_keep',
                    'payment_method' => 'pm_new',
                    'customer'       => 'cus_new',
                    'receipt_url'    => 'https://example.test/new-receipt',
                    'billing_details' => (object) [
                        'email' => 'donor@example.test',
                        'name'  => 'Test Donor',
                    ],
                    'payment_method_details' => (object) [
                        'card' => (object) [
                            'brand'   => 'visa',
                            'last4'   => '4242',
                            'country' => 'US',
                            'funding' => 'credit',
                        ],
                    ],
                ],
            ],
        ];

        (new StripeWebhookController())->handleEvent($event);

        $tx->refresh();

        $this->assertSame('ch_new', $tx->charge_id);
        $this->assertSame('pm_old', $tx->payment_method_id);
        $this->assertSame('cus_old', $tx->customer_id);
    }

    public function test_payment_intent_succeeded_does_not_explode_when_latest_charge_missing(): void
    {
        $tx = Transaction::factory()->create([
            'payment_intent_id' => 'pi_no_latest',
            'status'            => 'pending',
            'paid_at'           => null,
        ]);

        $event = (object) [
            'type' => 'payment_intent.succeeded',
            'data' => (object) [
                'object' => (object) [
                    'id' => 'pi_no_latest',
                ],
            ],
        ];

        (new StripeWebhookController())->handleEvent($event);

        $tx->refresh();

        $this->assertSame('succeeded', $tx->status);
        $this->assertNotNull($tx->paid_at);
    }

    // ------------------------------------------------------------------
    // Additional coverage
    // ------------------------------------------------------------------

    public function test_payment_intent_failed_sets_transaction_failed(): void
    {
        $tx = Transaction::factory()->create([
            'payment_intent_id' => 'pi_fail',
            'status'            => 'pending',
        ]);

        $event = (object)[
            'type' => 'payment_intent.payment_failed',
            'data' => (object)[
                'object' => (object)[
                    'id' => 'pi_fail',
                ],
            ],
        ];

        (new StripeWebhookController())->handleEvent($event);

        $this->assertDatabaseHas('transactions', [
            'id'     => $tx->id,
            'status' => 'failed',
        ]);
    }

    public function test_charge_succeeded_ignores_when_payment_intent_missing(): void
    {
        $tx = Transaction::factory()->create([
            'payment_intent_id' => 'pi_123',
            'status'            => 'succeeded',
            'charge_id'         => null,
        ]);

        $event = (object)[
            'type' => 'charge.succeeded',
            'data' => (object)[
                'object' => (object)[
                    'id' => 'ch_no_pi',
                    // payment_intent intentionally missing
                ],
            ],
        ];

        (new StripeWebhookController())->handleEvent($event);

        $tx->refresh();

        $this->assertNull($tx->charge_id);
    }

    public function test_invoice_paid_does_not_clobber_existing_customer_or_payment_method_ids(): void
    {
        $pledge = Pledge::forceCreate([
            'amount_cents'           => 1000,
            'currency'               => 'usd',
            'interval'               => 'month',
            'status'                 => 'incomplete',
            'donor_email'            => 'donor@example.test',
            'donor_name'             => 'Test Donor',
            'stripe_subscription_id' => 'sub_keep',
            'stripe_customer_id'     => 'cus_keep',
        ]);

        $tx = Transaction::factory()->create([
            'pledge_id'         => $pledge->id,
            'subscription_id'   => 'sub_keep',
            'payment_intent_id' => 'pi_keep',
            'customer_id'       => 'cus_old',
            'payment_method_id' => 'pm_old',
            'type'              => 'subscription_recurring',
            'status'            => 'succeeded',
        ]);

        $event = (object)[
            'type' => 'invoice.paid',
            'data' => (object)[
                'object' => (object)[
                    'id'           => 'in_keep',
                    'subscription' => 'sub_keep',
                    'customer'     => 'cus_new',

                    // Real Stripe: payment_intent is usually a string unless expanded,
                    // but controller handles either shape. We send expanded shape here.
                    'payment_intent' => (object)[
                        'id'             => 'pi_keep',
                        'payment_method' => 'pm_new',
                    ],

                    'amount_paid' => 1000,
                    'currency'    => 'usd',
                    'charge'      => 'ch_keep',
                    'lines'       => (object)['data' => [(object)['subscription' => 'sub_keep']]],
                ],
            ],
        ];

        (new StripeWebhookController())->handleEvent($event);

        $tx->refresh();

        $this->assertSame('cus_old', $tx->customer_id);
        $this->assertSame('pm_old', $tx->payment_method_id);
    }

    public function test_invoice_paid_merges_metadata(): void
    {
        $pledge = Pledge::forceCreate([
            'amount_cents'           => 500,
            'currency'               => 'usd',
            'interval'               => 'month',
            'status'                 => 'incomplete',
            'donor_email'            => 'donor@example.test',
            'donor_name'             => 'Test Donor',
            'stripe_subscription_id' => 'sub_meta',
            'stripe_customer_id'     => 'cus_meta',
        ]);

        $tx = Transaction::factory()->create([
            'payment_intent_id' => 'pi_meta',
            'metadata'          => ['foo' => 'bar'],
        ]);

        $event = (object)[
            'type' => 'invoice.paid',
            'data' => (object)[
                'object' => (object)[
                    'id'             => 'in_meta',
                    'subscription'   => 'sub_meta',
                    'customer'       => 'cus_meta',
                    'payment_intent' => 'pi_meta',
                    'amount_paid'    => 500,
                    'currency'       => 'usd',
                    'charge'         => 'ch_meta',
                    'lines'          => (object)['data' => [(object)['subscription' => 'sub_meta']]],
                ],
            ],
        ];

        (new StripeWebhookController())->handleEvent($event);

        $tx->refresh();

        $this->assertSame('bar', $tx->metadata['foo']);
        $this->assertSame('in_meta', $tx->metadata['stripe_invoice_id']);
        $this->assertSame('sub_meta', $tx->metadata['stripe_subscription_id']);
    }

    public function test_subscription_created_routes_to_handler_and_updates_status(): void
    {
        $pledge = Pledge::forceCreate([
            'stripe_subscription_id' => 'sub_created',
            'status'                 => 'incomplete',
            'cancel_at_period_end'   => false,
        ]);

        $event = (object)[
            'type' => 'customer.subscription.created',
            'data' => (object)[
                'object' => (object)[
                    'id'                   => 'sub_created',
                    'status'               => 'active',
                    'cancel_at_period_end' => false,

                    // Stripe always includes these keys (can be null)
                    'current_period_start' => null,
                    'current_period_end'   => null,
                ],
            ],
        ];

        (new StripeWebhookController())->handleEvent($event);

        $pledge->refresh();

        $this->assertSame('active', $pledge->status);
    }

    public function test_subscription_deleted_routes_to_handler_and_updates_status(): void
    {
        $pledge = Pledge::forceCreate([
            'stripe_subscription_id' => 'sub_deleted',
            'status'                 => 'active',
        ]);

        $event = (object)[
            'type' => 'customer.subscription.deleted',
            'data' => (object)[
                'object' => (object)[
                    'id'                   => 'sub_deleted',
                    'status'               => 'canceled',
                    'cancel_at_period_end' => false,

                    // Stripe always includes these keys (can be null)
                    'current_period_start' => null,
                    'current_period_end'   => null,
                ],
            ],
        ];

        (new StripeWebhookController())->handleEvent($event);

        $pledge->refresh();

        $this->assertSame('canceled', $pledge->status);
    }

    public function test_invoice_payment_failed_noops_when_pledge_missing(): void
    {
        $event = (object)[
            'type' => 'invoice.payment_failed',
            'data' => (object)[
                'object' => (object)[
                    'id'           => 'in_missing',
                    'subscription' => 'sub_missing',
                ],
            ],
        ];

        (new StripeWebhookController())->handleEvent($event);

        $this->assertDatabaseCount('pledges', 0);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_early_charge_then_invoice_paid_preserves_payment_intent_and_charge_ids(): void
    {
        $user = User::factory()->create();

        $pledge = Pledge::forceCreate([
            'user_id'                => $user->id,
            'amount_cents'           => 10000,
            'currency'               => 'usd',
            'interval'               => 'month',
            'status'                 => 'incomplete',
            'donor_email'            => 'donor@example.test',
            'donor_name'             => 'Test Donor',
            'stripe_subscription_id' => 'sub_real',
            'stripe_customer_id'     => 'cus_real',
        ]);

        // Pretend charge.succeeded ran FIRST and created an “early charge” tx:
        $earlyTx = Transaction::factory()->create([
            'user_id'           => $user->id,
            'pledge_id'         => $pledge->id,
            'type'              => 'subscription_recurring',
            'subscription_id'   => null,          // early charge doesn't yet know sub
            'payment_intent_id' => 'pi_early',
            'charge_id'         => 'ch_early',
            'customer_id'       => 'cus_real',
            'payment_method_id' => null,
            'amount_cents'      => 10000,
            'currency'          => 'usd',
            'status'            => 'succeeded',
            'metadata'          => [],
            'source'            => 'stripe_webhook',
        ]);

        $periodStart = now()->timestamp;
        $periodEnd   = now()->addMonth()->timestamp;

        // Realistic invoice.payment_succeeded payload:
        // - NO invoice.subscription
        // - NO lines.data.0.subscription
        // - subscription lives under lines.data.0.parent.subscription_item_details.subscription
        // - NO payment_intent or charge on invoice
        $invoice = (object) [
            'id'                 => 'in_real',
            'customer'           => 'cus_real',
            'customer_email'     => 'donor@example.test',
            'status'             => 'paid',
            'amount_paid'        => 10000,
            'currency'           => 'usd',
            'hosted_invoice_url' => 'https://example.test/invoices/in_real',
            'lines'              => (object) [
                'data' => [
                    (object) [
                        'period' => (object) [
                            'start' => $periodStart,
                            'end'   => $periodEnd,
                        ],
                        'parent' => (object) [
                            'subscription_item_details' => (object) [
                                'subscription' => 'sub_real',
                            ],
                        ],
                    ],
                ],
            ],
            'parent' => (object) [
                'subscription_details' => (object) [
                    'subscription' => 'sub_real',
                ],
            ],
            // intentionally missing: payment_intent, charge
        ];

        $event = (object) [
            'type' => 'invoice.payment_succeeded',
            'data' => (object) [
                'object' => $invoice,
            ],
        ];

        (new StripeWebhookController())->handleEvent($event);

        $earlyTx->refresh();

        // PI + charge preserved (invoice had nulls)
        $this->assertSame('pi_early', $earlyTx->payment_intent_id);
        $this->assertSame('ch_early', $earlyTx->charge_id);

        // subscription attached from invoice lines parent
        $this->assertSame('sub_real', $earlyTx->subscription_id);

        // invoice metadata merged
        $this->assertSame('in_real', $earlyTx->metadata['stripe_invoice_id']);
        $this->assertSame('sub_real', $earlyTx->metadata['stripe_subscription_id']);

        // still succeeded
        $this->assertSame('succeeded', $earlyTx->status);
    }

    public function test_invoice_paid_does_not_clobber_existing_payment_intent_or_charge_with_nulls(): void
    {
        $pledge = Pledge::forceCreate([
            'user_id'                => null,
            'amount_cents'           => 1000,
            'currency'               => 'usd',
            'interval'               => 'month',
            'status'                 => 'active',
            'donor_email'            => 'donor@example.test',
            'donor_name'             => 'Test Donor',
            'stripe_subscription_id' => 'sub_keep_pi',
            'stripe_customer_id'     => 'cus_keep_pi',
        ]);

        $tx = Transaction::factory()->create([
            'pledge_id'         => $pledge->id,
            'subscription_id'   => 'sub_keep_pi',
            'type'              => 'subscription_recurring',
            'status'            => 'succeeded',
            'payment_intent_id' => 'pi_old',
            'charge_id'         => 'ch_old',
        ]);

        $event = (object)[
            'type' => 'invoice.paid',
            'data' => (object)[
                'object' => (object)[
                    'id'             => 'in_keep_pi',
                    'subscription'   => null,
                    'customer'       => 'cus_keep_pi',
                    'payment_intent' => null,  // incoming nulls
                    'charge'         => null,
                    'amount_paid'    => 1000,
                    'currency'       => 'usd',
                    'lines' => (object)['data' => [(object)[]]],
                ],
            ],
        ];

        (new StripeWebhookController())->handleEvent($event);

        $tx->refresh();

        $this->assertSame('pi_old', $tx->payment_intent_id);
        $this->assertSame('ch_old', $tx->charge_id);
    }

    public function test_invoice_payment_paid_is_ignored_and_does_not_warn_or_mutate(): void
    {
        $pledge = Pledge::forceCreate([
            'amount_cents'           => 1000,
            'currency'               => 'usd',
            'interval'               => 'month',
            'status'                 => 'active',
            'stripe_subscription_id' => 'sub_ignore',
            'stripe_customer_id'     => 'cus_ignore',
        ]);

        $event = (object)[
            'type' => 'invoice_payment.paid',
            'data' => (object)[
                'object' => (object)[
                    'id' => 'inpay_123',
                    // This object is NOT an invoice and doesn't have what handleInvoicePaid needs.
                    'invoice' => 'in_abc', // even if present, we ignore currently
                ],
            ],
        ];

        (new StripeWebhookController())->handleEvent($event);

        // Should not create new tx or alter pledge
        $this->assertDatabaseCount('transactions', 0);
        $pledge->refresh();
        $this->assertSame('active', $pledge->status);
    }
}
