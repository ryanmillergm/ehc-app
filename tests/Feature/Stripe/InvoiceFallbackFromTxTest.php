<?php

namespace Tests\Feature\Stripe;

use App\Http\Controllers\StripeWebhookController;
use App\Models\Pledge;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InvoiceFallbackFromTxTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function invoice_paid_can_resolve_pledge_via_existing_transaction_when_invoice_missing_customer_and_subscription(): void
    {
        $pledge = Pledge::forceCreate([
            'user_id'                => null,
            'amount_cents'           => 1000,
            'currency'               => 'usd',
            'interval'               => 'month',
            'status'                 => 'incomplete',
            'stripe_customer_id'     => 'cus_test',
            'stripe_subscription_id' => 'sub_test',
            'donor_email'            => 'test@example.com',
            'donor_name'             => 'Test Person',
        ]);

        $tx = Transaction::forceCreate([
            'user_id'           => null,
            'pledge_id'         => $pledge->id,
            'type'              => 'subscription_recurring',
            'status'            => 'succeeded',
            'payment_intent_id' => 'pi_match',
            'charge_id'         => 'ch_match',
            'customer_id'       => 'cus_test',
            'subscription_id'   => null,
            'amount_cents'      => 1000,
            'currency'          => 'usd',
            'metadata'          => [],
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        $now = time();

        $invoice = (object) [
            'id' => 'inpay_123',
            // missing customer/subscription on purpose
            'payment_intent' => 'pi_match',
            'hosted_invoice_url' => 'https://example.test/invoice/inpay_123',
            'amount_paid' => 1000,
            'currency' => 'usd',
            'lines' => (object) [
                'data' => [
                    (object) [
                        'period' => (object) [
                            'start' => $now,
                            'end'   => $now + 3600,
                        ],
                    ],
                ],
            ],
            'status_transitions' => (object) [
                'paid_at' => $now,
            ],
        ];

        $event = (object) [
            'type' => 'invoice.payment_succeeded',
            'data' => (object) [
                'object' => $invoice,
            ],
        ];

        (new StripeWebhookController())->handleEvent($event);

        $this->assertDatabaseCount('transactions', 1);

        $tx->refresh();

        $this->assertSame('https://example.test/invoice/inpay_123', $tx->receipt_url);
        $this->assertSame('inpay_123', data_get($tx->metadata, 'stripe_invoice_id'));

        $pledge->refresh();
        $this->assertSame('active', $pledge->status);
        $this->assertNotNull($pledge->current_period_start);
        $this->assertNotNull($pledge->current_period_end);
    }
}
