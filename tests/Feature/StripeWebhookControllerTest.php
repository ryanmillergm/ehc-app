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

        (new StripeWebhookController())->handleEvent($event);

        $tx->refresh();

        $this->assertSame('succeeded', $tx->status);
        $this->assertNotNull($tx->paid_at);
    }

    public function test_invoice_paid_creates_subscription_recurring_transaction_and_updates_pledge(): void
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

        // deterministic timestamps
        $periodStart = 1_700_000_000;
        $periodEnd   = $periodStart + 2_592_000;
        $paidAtTs    = $periodStart + 120;

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
                    'billing_reason'     => 'subscription_cycle',

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
        $this->assertEquals($expectedPaidAt->timestamp, $pledge->last_pledge_at->timestamp);
        $this->assertEquals($expectedEnd->timestamp, $pledge->next_pledge_at->timestamp);
        $this->assertTrue($pledge->last_pledge_at->lt($pledge->next_pledge_at));

        $tx = Transaction::where('payment_intent_id', 'pi_456')->firstOrFail();

        $this->assertSame($pledge->id, $tx->pledge_id);
        $this->assertSame('subscription_recurring', $tx->type);
        $this->assertSame('succeeded', $tx->status);
        $this->assertSame('ch_456', $tx->charge_id);
        $this->assertSame('cus_123', $tx->customer_id);
        $this->assertSame('https://example.test/invoices/in_123', $tx->receipt_url);

        $meta = $this->meta($tx->metadata);
        $this->assertSame('in_123', $meta['stripe_invoice_id'] ?? null);
        $this->assertSame('sub_123', $meta['stripe_subscription_id'] ?? null);
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
                    'subscription'       => null,
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

        (new StripeWebhookController())->handleEvent($event);

        $pledge->refresh();

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

        (new StripeWebhookController())->handleEvent($event);

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

        (new StripeWebhookController())->handleEvent($event);

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

        (new StripeWebhookController())->handleEvent($event);

        $pledge->refresh();

        $this->assertSame('active', $pledge->status);
        $this->assertTrue($pledge->cancel_at_period_end);
        $this->assertEquals($existingStart->timestamp, $pledge->current_period_start->timestamp);
        $this->assertEquals($existingEnd->timestamp, $pledge->current_period_end->timestamp);
        $this->assertEquals($existingEnd->timestamp, $pledge->next_pledge_at->timestamp);
    }

    public function test_charge_succeeded_enriches_existing_transaction_with_charge_details_and_card_exp(): void
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
                            'brand'     => 'visa',
                            'last4'     => '4242',
                            'country'   => 'US',
                            'funding'   => 'credit',
                            'exp_month' => 12,
                            'exp_year'  => 2026,
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

        $meta = $this->meta($tx->metadata);
        $this->assertSame('visa', $meta['card_brand'] ?? null);
        $this->assertSame(12, $meta['card_exp_month'] ?? null);
        $this->assertSame(2026, $meta['card_exp_year'] ?? null);
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
                            (object) ['id' => 'ch_from_charges'],
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

    public function test_payment_intent_failed_sets_transaction_failed(): void
    {
        $tx = Transaction::factory()->create([
            'payment_intent_id' => 'pi_fail',
            'status'            => 'pending',
        ]);

        $event = (object) [
            'type' => 'payment_intent.payment_failed',
            'data' => (object) [
                'object' => (object) [
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

        $event = (object) [
            'type' => 'charge.succeeded',
            'data' => (object) [
                'object' => (object) [
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

        $event = (object) [
            'type' => 'invoice.paid',
            'data' => (object) [
                'object' => (object) [
                    'id'           => 'in_keep',
                    'subscription' => 'sub_keep',
                    'customer'     => 'cus_new',
                    'payment_intent' => (object) [
                        'id'             => 'pi_keep',
                        'payment_method' => 'pm_new',
                    ],
                    'amount_paid' => 1000,
                    'currency'    => 'usd',
                    'charge'      => 'ch_keep',
                    'lines'       => (object) ['data' => [(object) ['subscription' => 'sub_keep']]],
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
        Pledge::forceCreate([
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

        $event = (object) [
            'type' => 'invoice.paid',
            'data' => (object) [
                'object' => (object) [
                    'id'             => 'in_meta',
                    'subscription'   => 'sub_meta',
                    'customer'       => 'cus_meta',
                    'payment_intent' => 'pi_meta',
                    'amount_paid'    => 500,
                    'currency'       => 'usd',
                    'charge'         => 'ch_meta',
                    'lines'          => (object) ['data' => [(object) ['subscription' => 'sub_meta']]],
                ],
            ],
        ];

        (new StripeWebhookController())->handleEvent($event);

        $tx->refresh();

        $meta = $this->meta($tx->metadata);
        $this->assertSame('bar', $meta['foo'] ?? null);
        $this->assertSame('in_meta', $meta['stripe_invoice_id'] ?? null);
        $this->assertSame('sub_meta', $meta['stripe_subscription_id'] ?? null);
    }

    public function test_subscription_created_routes_to_handler_and_updates_status(): void
    {
        $pledge = Pledge::forceCreate([
            'stripe_subscription_id' => 'sub_created',
            'status'                 => 'incomplete',
            'cancel_at_period_end'   => false,
        ]);

        $event = (object) [
            'type' => 'customer.subscription.created',
            'data' => (object) [
                'object' => (object) [
                    'id'                   => 'sub_created',
                    'status'               => 'active',
                    'cancel_at_period_end' => false,
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

        $event = (object) [
            'type' => 'customer.subscription.deleted',
            'data' => (object) [
                'object' => (object) [
                    'id'                   => 'sub_deleted',
                    'status'               => 'canceled',
                    'cancel_at_period_end' => false,
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
        $event = (object) [
            'type' => 'invoice.payment_failed',
            'data' => (object) [
                'object' => (object) [
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

        // Existing tx already has PI + charge (strong keys)
        $earlyTx = Transaction::factory()->create([
            'user_id'           => $user->id,
            'pledge_id'         => $pledge->id,
            'type'              => 'subscription_recurring',
            'subscription_id'   => null,
            'payment_intent_id' => 'pi_early',
            'charge_id'         => 'ch_early',
            'customer_id'       => 'cus_real',
            'payment_method_id' => null,
            'amount_cents'      => 10000,
            'currency'          => 'usd',
            'status'            => 'succeeded',
            'metadata'          => [],
            'source'            => 'stripe_webhook',
            'receipt_url'       => null,
        ]);

        $periodStart = now()->timestamp;
        $periodEnd   = now()->addMonth()->timestamp;

        // Stripe invoice events normally include payment_intent.
        // We intentionally omit charge here to verify null-stomp protection.
        $invoice = (object) [
            'id'                 => 'in_real',
            'customer'           => 'cus_real',
            'customer_email'     => 'donor@example.test',
            'status'             => 'paid',
            'amount_paid'        => 10000,
            'currency'           => 'usd',
            'hosted_invoice_url' => 'https://example.test/invoices/in_real',

            'billing_reason'     => 'subscription_cycle',

            // strong key present
            'payment_intent'     => 'pi_early',

            // intentionally missing/null to ensure we don't clobber existing ch_early
            'charge'             => null,

            // supply subscription via nested paths (your resolver supports these)
            'parent' => (object) [
                'subscription_details' => (object) [
                    'subscription' => 'sub_real',
                ],
            ],

            'lines' => (object) [
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
        ];

        $event = (object) [
            'type' => 'invoice.payment_succeeded',
            'data' => (object) [
                'object' => $invoice,
            ],
        ];

        (new StripeWebhookController())->handleEvent($event);

        $earlyTx->refresh();

        // PI/charge must be preserved
        $this->assertSame('pi_early', $earlyTx->payment_intent_id);
        $this->assertSame('ch_early', $earlyTx->charge_id);

        // subscription id should be populated from invoice nested subscription details
        $this->assertSame('sub_real', $earlyTx->subscription_id);

        // invoice metadata should be merged
        $meta = $this->meta($earlyTx->metadata);
        $this->assertSame('in_real', $meta['stripe_invoice_id'] ?? null);
        $this->assertSame('sub_real', $meta['stripe_subscription_id'] ?? null);

        // status should remain succeeded
        $this->assertSame('succeeded', $earlyTx->status);

        // hosted invoice url should populate receipt_url when present
        $this->assertSame('https://example.test/invoices/in_real', $earlyTx->receipt_url);

        // still no duplicate tx rows created
        $this->assertSame(1, Transaction::query()->where('payment_intent_id', 'pi_early')->count());
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

        $event = (object) [
            'type' => 'invoice.paid',
            'data' => (object) [
                'object' => (object) [
                    'id'             => 'in_keep_pi',
                    'subscription'   => null,
                    'customer'       => 'cus_keep_pi',
                    'payment_intent' => null,
                    'charge'         => null,
                    'amount_paid'    => 1000,
                    'currency'       => 'usd',
                    'lines' => (object) ['data' => [(object) []]],
                ],
            ],
        ];

        (new StripeWebhookController())->handleEvent($event);

        $tx->refresh();

        $this->assertSame('pi_old', $tx->payment_intent_id);
        $this->assertSame('ch_old', $tx->charge_id);
    }

    public function test_invoice_payment_paid_enriches_placeholder_tx_and_updates_pledge_latest_fields(): void
    {
        $pledge = Pledge::forceCreate([
            'user_id'                => null,
            'amount_cents'           => 1200,
            'currency'               => 'usd',
            'interval'               => 'month',
            'status'                 => 'incomplete',
            'donor_email'            => 'donor@example.test',
            'donor_name'             => 'Test Donor',
            'stripe_subscription_id' => 'sub_inpay',
            'stripe_customer_id'     => 'cus_inpay',
            'metadata'               => [],
        ]);

        // Placeholder tx stores stripe_invoice_id in metadata (so webhook can find it)
        $placeholder = Transaction::factory()->create([
            'pledge_id'         => $pledge->id,
            'type'              => 'subscription_initial',
            'status'            => 'pending',
            'payment_intent_id' => null,
            'charge_id'         => null,
            'customer_id'       => 'cus_inpay',
            'paid_at'           => null,
            'metadata'          => [
                'stage'             => 'subscription_creation',
                'stripe_invoice_id' => 'in_inpay_1',
            ],
        ]);

        $paidAtTs = 1_700_000_123; // 2023-11-14 22:15:23 UTC

        $event = (object) [
            'type' => 'invoice_payment.paid',
            'data' => (object) [
                'object' => (object) [
                    'id'      => 'inpay_1',
                    'invoice' => 'in_inpay_1',
                    'payment' => (object) [
                        'payment_intent' => 'pi_from_inpay',
                    ],
                    'status_transitions' => (object) [
                        'paid_at' => $paidAtTs,
                    ],
                ],
            ],
        ];

        (new StripeWebhookController())->handleEvent($event);

        $pledge->refresh();
        $placeholder->refresh();

        // Pledge updated
        $this->assertSame('in_inpay_1', $pledge->latest_invoice_id);
        $this->assertSame('pi_from_inpay', $pledge->latest_payment_intent_id);
        $this->assertNotNull($pledge->last_pledge_at);
        $this->assertEquals($paidAtTs, $pledge->last_pledge_at->timestamp);

        // Placeholder tx enriched
        $this->assertSame('pi_from_inpay', $placeholder->payment_intent_id);

        // NOTE: your current code keeps status "pending" because it uses `$tx->status ?: 'succeeded'`
        $this->assertSame('succeeded', $placeholder->status);

        $this->assertNotNull($placeholder->paid_at);
        $this->assertEquals($paidAtTs, $placeholder->paid_at->timestamp);

        $meta = is_array($placeholder->metadata)
            ? $placeholder->metadata
            : (json_decode((string) $placeholder->metadata, true) ?: []);

        $this->assertSame('in_inpay_1', $meta['stripe_invoice_id'] ?? null);
        $this->assertSame('inpay_1', $meta['stripe_invoice_payment_id'] ?? null);
    }

    // -----------------------------
    // Helpers
    // -----------------------------

    private function meta($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }
}
