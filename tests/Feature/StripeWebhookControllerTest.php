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

    public function test_payment_intent_succeeded_updates_transaction(): void
    {
        $tx = Transaction::factory()->create([
            'payment_intent_id' => 'pi_123',
            'status'            => 'pending',
        ]);

        $event = (object) [
            'type' => 'payment_intent.succeeded',
            'data' => (object) [
                'object' => (object) [
                    'id'      => 'pi_123',
                    'charges' => (object) [
                        'data' => [
                            (object) [
                                'id'          => 'ch_123',
                                'receipt_url' => 'https://example.test/receipt',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $controller = new StripeWebhookController();
        $controller->handleEvent($event);

        $this->assertDatabaseHas('transactions', [
            'id'        => $tx->id,
            'status'    => 'succeeded',
            'charge_id' => 'ch_123',
        ]);
    }

    public function test_invoice_paid_creates_recurring_transaction_and_updates_pledge(): void
    {
        // Minimal pledge row that matches what the controller expects
        $pledge = Pledge::forceCreate([
            'user_id'               => null,
            'amount_cents'          => 1500,
            'currency'              => 'usd',
            'interval'              => 'month',
            'status'                => 'incomplete',
            'donor_email'           => 'donor@example.test',
            'donor_name'            => 'Test Donor',
            'stripe_subscription_id'=> 'sub_123',
        ]);

        $periodStart = 1_700_000_000;
        $periodEnd   = $periodStart + 2_592_000; // ~30 days

        $event = (object) [
            'type' => 'invoice.paid',
            'data' => (object) [
                'object' => (object) [
                    'id'              => 'in_123',
                    'subscription'    => 'sub_123',
                    'payment_intent'  => 'pi_456',
                    'amount_paid'     => 1500,
                    'currency'        => 'usd',
                    'charge'          => 'ch_456',
                    'customer_email'  => 'alt@example.test',
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
            'pledge_id'        => $pledge->id,
            'subscription_id'  => 'sub_123',
            'payment_intent_id'=> 'pi_456',
            'charge_id'        => 'ch_456',
            'amount_cents'     => 1500,
            'currency'         => 'usd',
            'type'             => 'subscription_recurring',
            'status'           => 'succeeded',
            'source'           => 'stripe_webhook',
        ]);

        // Pledge is marked active and latest_invoice_id stored
        $this->assertDatabaseHas('pledges', [
            'id'               => $pledge->id,
            'status'           => 'active',
            'latest_invoice_id'=> 'in_123',
        ]);
    }

    public function test_invoice_payment_failed_marks_pledge_past_due(): void
    {
        $pledge = Pledge::forceCreate([
            'user_id'               => null,
            'amount_cents'          => 1500,
            'currency'              => 'usd',
            'interval'              => 'month',
            'status'                => 'active',
            'donor_email'           => 'donor@example.test',
            'donor_name'            => 'Test Donor',
            'stripe_subscription_id'=> 'sub_999',
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
