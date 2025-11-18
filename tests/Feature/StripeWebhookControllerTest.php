<?php

namespace Tests\Feature;

use App\Http\Controllers\StripeWebhookController;
use App\Models\Pledge;
use App\Models\Refund;
use App\Models\Transaction;
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
        ]);

        $periodStart = 1_700_000_000;
        $periodEnd   = $periodStart + 2_592_000; // ~30 days

        $event = (object) [
            'type' => 'invoice.paid',
            'data' => (object) [
                'object' => (object) [
                    'id'                 => 'in_123',
                    'subscription'       => 'sub_123',
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
        ]);

        // Pledge is marked active and latest_invoice_id stored
        $this->assertDatabaseHas('pledges', [
            'id'                => $pledge->id,
            'status'            => 'active',
            'latest_invoice_id' => 'in_123',
        ]);

        // And the transaction has the hosted invoice URL as the receipt_url
        $tx = Transaction::where('payment_intent_id', 'pi_456')->firstOrFail();
        $this->assertSame('https://example.test/invoices/in_123', $tx->receipt_url);
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

        $controller = new StripeWebhookController();
        $controller->handleEvent($event);

        $tx->refresh();

        $this->assertSame('ch_123', $tx->charge_id);
        $this->assertSame('https://example.test/stripe-receipt', $tx->receipt_url);
        $this->assertSame('donor@example.test', $tx->payer_email);
        $this->assertSame('Test Donor', $tx->payer_name);
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

        $controller = new StripeWebhookController();
        $controller->handleEvent($event);

        // Transaction is marked refunded
        $this->assertDatabaseHas('transactions', [
            'id'     => $tx->id,
            'status' => 'refunded',
        ]);

        // Refund row created
        $this->assertDatabaseHas('refunds', [
            'transaction_id'   => $tx->id,
            'stripe_refund_id' => 're_123',
            'charge_id'        => 'ch_123',
            'amount_cents'     => 1500,
            'currency'         => 'usd',
            'status'           => 'succeeded',
        ]);

        // Sanity check: exactly one refund
        $this->assertSame(1, Refund::count());
    }
}
