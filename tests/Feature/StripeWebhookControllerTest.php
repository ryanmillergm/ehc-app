<?php

namespace Tests\Feature;

use App\Http\Controllers\StripeWebhookController;
use App\Models\Pledge;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StripeWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_intent_succeeded_marks_transaction_succeeded_and_sets_paid_at(): void
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

        $this->assertDatabaseHas('transactions', [
            'id'     => $tx->id,
            'status' => 'succeeded',
        ]);

        $this->assertNotNull($tx->fresh()->paid_at);
    }

    public function test_payment_intent_succeeded_with_unknown_transaction_is_ignored(): void
    {
        // no transactions in DB
        $this->assertDatabaseCount('transactions', 0);

        $event = (object) [
            'type' => 'payment_intent.succeeded',
            'data' => (object) [
                'object' => (object) [
                    'id' => 'pi_missing',
                ],
            ],
        ];

        $controller = new StripeWebhookController();

        // Should not throw and should not create any rows
        $controller->handleEvent($event);

        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_charge_succeeded_enriches_existing_transaction(): void
    {
        $tx = Transaction::factory()->create([
            'payment_intent_id' => 'pi_123',
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
                        'name'  => 'Donor Name',
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

        $fresh = $tx->fresh();

        $this->assertSame('ch_123', $fresh->charge_id);
        $this->assertSame('https://example.test/stripe-receipt', $fresh->receipt_url);
        $this->assertSame('donor@example.test', $fresh->payer_email);
        $this->assertSame('Donor Name', $fresh->payer_name);

        $this->assertIsArray($fresh->metadata);
        $this->assertSame('visa', $fresh->metadata['card_brand'] ?? null);
        $this->assertSame('4242', $fresh->metadata['card_last4'] ?? null);
        $this->assertSame('US', $fresh->metadata['card_country'] ?? null);
        $this->assertSame('credit', $fresh->metadata['card_funding'] ?? null);
        $this->assertSame('ch_123', $fresh->metadata['charge_id'] ?? null);
    }

    public function test_charge_succeeded_without_matching_transaction_is_ignored(): void
    {
        $event = (object) [
            'type' => 'charge.succeeded',
            'data' => (object) [
                'object' => (object) [
                    'id'             => 'ch_999',
                    'payment_intent' => 'pi_999',
                ],
            ],
        ];

        $controller = new StripeWebhookController();

        // No exception and no new transactions
        $controller->handleEvent($event);

        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_invoice_paid_creates_recurring_transaction_and_updates_pledge(): void
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
                    'lines' => (object) [
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
            'payer_email'       => 'alt@example.test',
            'payer_name'        => 'Test Donor',
            'receipt_url'       => 'https://example.test/invoices/in_123',
        ]);

        // Check metadata fields were stored
        $tx = Transaction::where('payment_intent_id', 'pi_456')->firstOrFail();
        $this->assertIsArray($tx->metadata);
        $this->assertSame('in_123', $tx->metadata['stripe_invoice_id'] ?? null);
        $this->assertSame('sub_123', $tx->metadata['stripe_subscription_id'] ?? null);

        // Pledge is marked active with latest invoice + PI
        $pledge = $pledge->fresh();
        $this->assertSame('active', $pledge->status);
        $this->assertSame('in_123', $pledge->latest_invoice_id);
        $this->assertSame('pi_456', $pledge->latest_payment_intent_id);
        $this->assertNotNull($pledge->last_pledge_at);
        $this->assertNotNull($pledge->next_pledge_at);
    }

    public function test_invoice_paid_is_idempotent_for_same_payment_intent(): void
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
        ]);

        $event = (object) [
            'type' => 'invoice.paid',
            'data' => (object) [
                'object' => (object) [
                    'id'             => 'in_123',
                    'subscription'   => 'sub_123',
                    'payment_intent' => 'pi_456',
                    'amount_paid'    => 1500,
                    'currency'       => 'usd',
                    'charge'         => 'ch_456',
                    'lines'          => (object) ['data' => []],
                ],
            ],
        ];

        $controller = new StripeWebhookController();

        // Process the same event twice (Stripe retries, etc.)
        $controller->handleEvent($event);
        $controller->handleEvent($event);

        $this->assertEquals(
            1,
            Transaction::where('payment_intent_id', 'pi_456')->count()
        );
    }

    public function test_invoice_without_subscription_does_not_create_transaction(): void
    {
        $event = (object) [
            'type' => 'invoice.paid',
            'data' => (object) [
                'object' => (object) [
                    'id'           => 'in_no_sub',
                    'subscription' => null,
                    'amount_paid'  => 500,
                    'currency'     => 'usd',
                ],
            ],
        ];

        $controller = new StripeWebhookController();
        $controller->handleEvent($event);

        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('pledges', 0);
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
}
