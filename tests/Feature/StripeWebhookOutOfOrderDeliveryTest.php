<?php

namespace Tests\Feature;

use App\Http\Controllers\StripeWebhookController;
use App\Models\Pledge;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StripeWebhookOutOfOrderDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_out_of_order_charge_succeeded_then_invoice_paid_creates_only_one_transaction_and_ends_succeeded(): void
    {
        $pledge = Pledge::forceCreate([
            'user_id'                => null,
            'amount_cents'           => 10000,
            'currency'               => 'usd',
            'interval'               => 'month',
            'status'                 => 'incomplete',
            'donor_email'            => 'donor@example.test',
            'donor_name'             => 'Test Donor',
            'stripe_subscription_id' => 'sub_real',
            'stripe_customer_id'     => 'cus_real',
        ]);

        $controller = new StripeWebhookController();

        // 1) charge.succeeded arrives FIRST
        $chargeEvent = (object) [
            'type' => 'charge.succeeded',
            'data' => (object) [
                'object' => (object) [
                    'id'              => 'ch_early',
                    'invoice'          => 'in_real',
                    'payment_intent'   => 'pi_early',
                    'customer'         => 'cus_real',
                    'payment_method'   => 'pm_early',
                    'amount'           => 10000,
                    'currency'         => 'usd',
                    'receipt_url'      => 'https://example.test/receipt/ch_early',
                    'billing_details'  => (object) [
                        'email' => 'donor@example.test',
                        'name'  => 'Test Donor',
                    ],
                ],
            ],
        ];

        $controller->handleEvent($chargeEvent);

        // Current controller behavior: subscription-like charge can create the tx.
        $this->assertSame(1, Transaction::count());

        $tx = Transaction::firstOrFail();

        $this->assertSame($pledge->id, $tx->pledge_id);
        $this->assertSame('sub_real', $tx->subscription_id);

        $this->assertSame('succeeded', $tx->status);
        $this->assertSame('pi_early', $tx->payment_intent_id);
        $this->assertSame('ch_early', $tx->charge_id);
        $this->assertSame('cus_real', $tx->customer_id);
        $this->assertSame('pm_early', $tx->payment_method_id);

        // receipt_url is from charge initially
        $this->assertSame('https://example.test/receipt/ch_early', $tx->receipt_url);

        // metadata should have invoice id if available
        $this->assertSame('in_real', data_get($tx->metadata, 'stripe_invoice_id'));
        $this->assertSame('sub_real', data_get($tx->metadata, 'stripe_subscription_id'));

        // 2) invoice.paid arrives AFTER (this enriches same tx)
        $periodStart = now()->timestamp;
        $periodEnd   = now()->addMonth()->timestamp;

        $invoiceEvent = (object) [
            'type' => 'invoice.paid',
            'data' => (object) [
                'object' => (object) [
                    'id'                 => 'in_real',
                    'billing_reason'     => 'subscription_create',
                    'subscription'       => 'sub_real',
                    'customer'           => 'cus_real',
                    'customer_email'     => 'donor@example.test',
                    'status'             => 'paid',
                    'amount_paid'        => 10000,
                    'currency'           => 'usd',
                    'hosted_invoice_url' => 'https://example.test/invoices/in_real',
                    'payment_intent'     => (object) [
                        'id'             => 'pi_early',
                        'payment_method' => 'pm_early',
                    ],
                    'charge' => 'ch_early',
                    'lines' => (object) [
                        'data' => [
                            (object) [
                                'subscription' => 'sub_real',
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

        $controller->handleEvent($invoiceEvent);

        // still exactly one tx
        $this->assertSame(1, Transaction::count());

        $tx->refresh();

        $this->assertSame('succeeded', $tx->status);
        $this->assertSame('pi_early', $tx->payment_intent_id);
        $this->assertSame('ch_early', $tx->charge_id);
        $this->assertSame('cus_real', $tx->customer_id);
        $this->assertSame('pm_early', $tx->payment_method_id);
        $this->assertSame('sub_real', $tx->subscription_id);

        // invoice should win for receipt_url
        $this->assertSame('https://example.test/invoices/in_real', $tx->receipt_url);

        // invoice metadata merged
        $this->assertIsArray($tx->metadata);
        $this->assertSame('in_real', $tx->metadata['stripe_invoice_id'] ?? null);
        $this->assertSame('sub_real', $tx->metadata['stripe_subscription_id'] ?? null);
        $this->assertSame('subscription_create', $tx->metadata['billing_reason'] ?? null);

        // subscription_create should resolve to initial
        $this->assertSame('subscription_initial', $tx->type);
    }

    public function test_out_of_order_charge_succeeded_then_invoice_paid_enriches_widget_created_tx_without_duplication_and_stays_succeeded(): void
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

        $pending = Transaction::factory()->create([
            'user_id'           => null,
            'pledge_id'         => $pledge->id,
            'subscription_id'   => 'sub_123',
            'payment_intent_id' => null,
            'charge_id'         => null,
            'customer_id'       => 'cus_123',
            'payment_method_id' => null,
            'amount_cents'      => 1500,
            'currency'          => 'usd',
            'type'              => 'subscription_initial',
            'status'            => 'pending',
            'source'            => 'donation_widget',
            'paid_at'           => null,
            'metadata'          => [],
            'created_at'        => now(),
        ]);

        $controller = new StripeWebhookController();

        $chargeEvent = (object) [
            'type' => 'charge.succeeded',
            'data' => (object) [
                'object' => (object) [
                    'id'              => 'ch_456',
                    'invoice'          => 'in_123',
                    'payment_intent'   => 'pi_456',
                    'customer'         => 'cus_123',
                    'payment_method'   => 'pm_456',
                    'amount'           => 1500,
                    'currency'         => 'usd',
                    'receipt_url'      => 'https://example.test/receipt/ch_456',
                    'billing_details'  => (object) [
                        'email' => 'donor@example.test',
                        'name'  => 'Test Donor',
                    ],
                ],
            ],
        ];

        $controller->handleEvent($chargeEvent);

        $this->assertSame(1, Transaction::where('pledge_id', $pledge->id)->count());

        $pending->refresh();
        $this->assertSame('succeeded', $pending->status);
        $this->assertSame('pi_456', $pending->payment_intent_id);
        $this->assertSame('ch_456', $pending->charge_id);

        $periodStart = now()->timestamp;
        $periodEnd   = now()->addMonth()->timestamp;

        $invoiceEvent = (object) [
            'type' => 'invoice.paid',
            'data' => (object) [
                'object' => (object) [
                    'id'                 => 'in_123',
                    'billing_reason'     => 'subscription_create',
                    'subscription'       => 'sub_123',
                    'customer'           => 'cus_123',
                    'customer_email'     => 'donor@example.test',
                    'status'             => 'paid',
                    'amount_paid'        => 1500,
                    'currency'           => 'usd',
                    'hosted_invoice_url' => 'https://example.test/invoices/in_123',
                    'payment_intent'     => (object) [
                        'id'             => 'pi_456',
                        'payment_method' => 'pm_456',
                    ],
                    'charge' => 'ch_456',
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

        $controller->handleEvent($invoiceEvent);

        $this->assertSame(1, Transaction::where('pledge_id', $pledge->id)->count());

        $pending->refresh();
        $this->assertSame('succeeded', $pending->status);
        $this->assertSame('pi_456', $pending->payment_intent_id);
        $this->assertSame('ch_456', $pending->charge_id);

        $this->assertSame('subscription_initial', $pending->type);
        $this->assertSame('https://example.test/invoices/in_123', $pending->receipt_url);

        $this->assertSame('in_123', $pending->metadata['stripe_invoice_id'] ?? null);
        $this->assertSame('sub_123', $pending->metadata['stripe_subscription_id'] ?? null);
        $this->assertSame('subscription_create', $pending->metadata['billing_reason'] ?? null);
    }

    public function test_charge_succeeded_then_invoice_paid_with_null_pi_and_charge_does_not_duplicate_or_clobber(): void
    {
        $pledge = Pledge::forceCreate([
            'user_id'                => null,
            'amount_cents'           => 10000,
            'currency'               => 'usd',
            'interval'               => 'month',
            'status'                 => 'incomplete',
            'donor_email'            => 'donor@example.test',
            'donor_name'             => 'Test Donor',
            'stripe_subscription_id' => 'sub_real',
            'stripe_customer_id'     => 'cus_real',
        ]);

        $pendingTx = Transaction::factory()->create([
            'user_id'           => null,
            'pledge_id'         => $pledge->id,
            'subscription_id'   => 'sub_real',
            'customer_id'       => 'cus_real',
            'type'              => 'subscription_recurring',
            'status'            => 'pending',
            'payment_intent_id' => null,
            'charge_id'         => null,
            'metadata'          => [],
            'source'            => 'donation_widget',
            'created_at'        => now(),
        ]);

        $chargeEvent = (object) [
            'type' => 'charge.succeeded',
            'data' => (object) [
                'object' => (object) [
                    'id'              => 'ch_early',
                    'invoice'          => 'in_real',
                    'customer'         => 'cus_real',
                    'payment_intent'   => 'pi_early',
                    'payment_method'   => 'pm_early',
                    'amount'           => 10000,
                    'currency'         => 'usd',
                    'receipt_url'      => 'https://example.test/receipt-early',
                    'billing_details'  => (object) [
                        'email' => 'donor@example.test',
                        'name'  => 'Test Donor',
                    ],
                ],
            ],
        ];

        (new StripeWebhookController())->handleEvent($chargeEvent);

        $this->assertSame(1, Transaction::where('pledge_id', $pledge->id)->count());

        $pendingTx->refresh();
        $this->assertSame('succeeded', $pendingTx->status);
        $this->assertSame('pi_early', $pendingTx->payment_intent_id);
        $this->assertSame('ch_early', $pendingTx->charge_id);
        $this->assertSame('in_real', data_get($pendingTx->metadata, 'stripe_invoice_id'));

        $periodStart = now()->timestamp;
        $periodEnd   = now()->addMonth()->timestamp;

        $invoiceEvent = (object) [
            'type' => 'invoice.paid',
            'data' => (object) [
                'object' => (object) [
                    'id'             => 'in_real',
                    'billing_reason' => 'subscription_cycle',
                    'customer'       => 'cus_real',
                    'payment_intent' => null,
                    'charge'         => null,
                    'amount_paid'        => 10000,
                    'currency'           => 'usd',
                    'customer_email'     => 'donor@example.test',
                    'hosted_invoice_url' => 'https://example.test/invoices/in_real',
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
                    'parent' => (object) [
                        'subscription_details' => (object) [
                            'subscription' => 'sub_real',
                        ],
                    ],
                ],
            ],
        ];

        (new StripeWebhookController())->handleEvent($invoiceEvent);

        $this->assertSame(1, Transaction::where('pledge_id', $pledge->id)->count());

        $pendingTx->refresh();

        $this->assertSame('pi_early', $pendingTx->payment_intent_id);
        $this->assertSame('ch_early', $pendingTx->charge_id);
        $this->assertSame('succeeded', $pendingTx->status);

        $this->assertSame('sub_real', $pendingTx->subscription_id);
        $this->assertSame('https://example.test/invoices/in_real', $pendingTx->receipt_url);
        $this->assertSame('subscription_recurring', $pendingTx->type);
    }
}
