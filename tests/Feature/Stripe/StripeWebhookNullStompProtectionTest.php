<?php

namespace Tests\Feature\Stripe;

use App\Http\Controllers\StripeWebhookController;
use App\Models\Pledge;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StripeWebhookNullStompProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_paid_with_null_pi_and_charge_creates_new_tx_keyed_by_invoice_and_does_not_stomp_existing_tx(): void
    {
        $pledge = Pledge::forceCreate([
            'user_id'                => null,
            'amount_cents'           => 1000,
            'currency'               => 'usd',
            'interval'               => 'month',
            'status'                 => 'active',
            'donor_email'            => 'donor@example.test',
            'donor_name'             => 'Test Donor',
            'stripe_subscription_id' => 'sub_keep',
            'stripe_customer_id'     => 'cus_keep',
        ]);

        // Existing (previous invoice) transaction stays intact
        $existing = Transaction::factory()->create([
            'pledge_id'         => $pledge->id,
            'subscription_id'   => 'sub_keep',
            'type'              => 'subscription_recurring',
            'status'            => 'succeeded',
            'payment_intent_id' => 'pi_keep',
            'charge_id'         => 'ch_keep',
            'customer_id'       => 'cus_keep',
            'stripe_invoice_id' => 'in_old',
            'receipt_url'       => null,
        ]);

        $periodStart = now()->timestamp;
        $periodEnd   = now()->addMonth()->timestamp;

        $event = (object) [
            'type' => 'invoice.paid',
            'data' => (object) [
                'object' => (object) [
                    'id'                 => 'in_new',
                    'billing_reason'     => 'subscription_cycle',
                    'subscription'       => 'sub_keep',
                    'customer'           => 'cus_keep',
                    'payment_intent'     => null,
                    'charge'             => null,
                    'amount_paid'        => 1000,
                    'currency'           => 'usd',
                    'hosted_invoice_url' => 'https://example.test/invoices/in_new',
                    'lines' => (object) [
                        'data' => [
                            (object) [
                                'subscription' => 'sub_keep',
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

        // We now expect TWO tx rows: old + new invoice tx
        $this->assertSame(2, Transaction::where('pledge_id', $pledge->id)->count());

        $existing->refresh();

        // Old tx is NOT stomped
        $this->assertSame('pi_keep', $existing->payment_intent_id);
        $this->assertSame('ch_keep', $existing->charge_id);
        $this->assertSame('in_old', $existing->stripe_invoice_id);
        $this->assertNull($existing->receipt_url);

        $newTx = Transaction::query()
            ->where('pledge_id', $pledge->id)
            ->where('stripe_invoice_id', 'in_new')
            ->firstOrFail();

        // New tx created for this invoice id (even though PI/charge are null)
        $this->assertSame('subscription_recurring', $newTx->type);
        $this->assertSame('succeeded', $newTx->status);

        $this->assertNull($newTx->payment_intent_id);
        $this->assertNull($newTx->charge_id);

        $this->assertSame('cus_keep', $newTx->customer_id);
        $this->assertSame('sub_keep', $newTx->subscription_id);
        $this->assertSame('https://example.test/invoices/in_new', $newTx->receipt_url);
    }
}
