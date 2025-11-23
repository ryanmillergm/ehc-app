<?php

namespace Tests\Feature;

use App\Http\Controllers\StripeWebhookController;
use App\Models\Pledge;
use App\Models\Refund;
use App\Models\Transaction;
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

    public function test_invoice_paid_creates_subscription_recurring_transaction_and_updates_pledge(): void
    {
        // Minimal pledge row that matches what the controller expects
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

        $periodStart = 1_700_000_000;
        $periodEnd   = $periodStart + 2_592_000; // ~30 days

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

        $controller = new StripeWebhookController();
        $controller->handleEvent($event);

        // A new recurring transaction is created
        $this->assertDatabaseHas('transactions', [
            'pledge_id'         => $pledge->id,
            'subscription_id'   => 'sub_123',
            'payment_intent_id' => 'pi_456',
            'charge_id'         => 'ch_456',
            'amount_cents'      => 1500,
            'currency'          => 'usd',
            'type'              => 'subscription_recurring',
            'status'            => 'succeeded',
            'source'            => 'stripe_webhook',
            'customer_id'       => 'cus_123',
        ]);

        // Pledge is marked active and latest_invoice_id stored
        $pledge->refresh();

        $this->assertSame('active', $pledge->status);
        $this->assertSame('in_123', $pledge->latest_invoice_id);
        $this->assertSame('pi_456', $pledge->latest_payment_intent_id);

        // Period & reporting fields updated from invoice line
        $expectedStart = Carbon::createFromTimestamp($periodStart);
        $expectedEnd   = Carbon::createFromTimestamp($periodEnd);

        $this->assertEquals($expectedStart->timestamp, $pledge->current_period_start->timestamp);
        $this->assertEquals($expectedEnd->timestamp, $pledge->current_period_end->timestamp);
        $this->assertEquals($expectedEnd->timestamp, $pledge->last_pledge_at->timestamp);
        $this->assertEquals($expectedEnd->timestamp, $pledge->next_pledge_at->timestamp);

        // And the transaction has the hosted invoice URL as the receipt_url
        $tx = Transaction::where('payment_intent_id', 'pi_456')->firstOrFail();
        $this->assertSame('https://example.test/invoices/in_123', $tx->receipt_url);
        $this->assertSame('alt@example.test', $tx->payer_email);
        $this->assertSame('Test Donor', $tx->payer_name);
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
}
