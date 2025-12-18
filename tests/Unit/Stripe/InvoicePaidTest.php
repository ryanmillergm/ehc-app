<?php

namespace Tests\Unit\Stripe;

use App\Http\Controllers\StripeWebhookController;
use App\Models\Pledge;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InvoicePaidTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function invoice_paid_updates_pledge_period_fields_and_upserts_transaction_idempotently(): void
    {
        $pledge = Pledge::factory()->create([
            'stripe_subscription_id' => 'sub_123',
            'stripe_customer_id'     => 'cus_123',
            'amount_cents'           => 301,
            'currency'               => 'usd',
            'status'                 => 'incomplete',
        ]);

        $invoice = (object) [
            'id'       => 'in_123',
            'customer' => 'cus_123',
            'subscription' => 'sub_123',
            'payment_intent' => 'pi_123',
            'charge'   => 'ch_123',
            'amount_paid' => 301,
            'currency' => 'usd',
            'hosted_invoice_url' => 'https://invoice.test/in_123',
            'billing_reason' => 'subscription_create',
            'status_transitions' => (object) ['paid_at' => 1700000000],
            'lines' => (object) [
                'data' => [
                    (object) [
                        'period' => (object) ['start' => 1700000000, 'end' => 1702592000],
                    ],
                ],
            ],
        ];

        $c = app(StripeWebhookController::class);

        // Run twice to simulate Stripe retries
        $c->handleEvent((object) ['type' => 'invoice.paid', 'data' => (object) ['object' => $invoice]]);
        $c->handleEvent((object) ['type' => 'invoice.paid', 'data' => (object) ['object' => $invoice]]);

        $pledge->refresh();
        $this->assertSame('active', $pledge->status);
        $this->assertNotNull($pledge->current_period_start);
        $this->assertNotNull($pledge->current_period_end);
        $this->assertNotNull($pledge->last_pledge_at);
        $this->assertNotNull($pledge->next_pledge_at);
        $this->assertSame('pi_123', $pledge->latest_payment_intent_id);

        $this->assertSame(1, Transaction::where('pledge_id', $pledge->id)->count());

        $tx = Transaction::where('pledge_id', $pledge->id)->firstOrFail();
        $this->assertSame('subscription_initial', $tx->type);
        $this->assertSame('succeeded', $tx->status);
        $this->assertNotNull($tx->paid_at);
        $this->assertSame('pi_123', $tx->payment_intent_id);
        $this->assertSame('ch_123', $tx->charge_id);
    }
}
