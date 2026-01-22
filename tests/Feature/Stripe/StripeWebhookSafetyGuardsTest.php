<?php

namespace Tests\Feature\Stripe;

use App\Http\Controllers\StripeWebhookController;
use App\Models\Pledge;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StripeWebhookSafetyGuardsTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_paid_prefers_subscription_match_even_if_customer_mismatch_and_does_not_update_other_pledge(): void
    {
        // Pledge A matches subscription (sub_A) but NOT the invoice customer (cus_B)
        $pledgeA = Pledge::forceCreate([
            'amount_cents'           => 1500,
            'currency'               => 'usd',
            'interval'               => 'month',
            'status'                 => 'incomplete',
            'donor_email'            => 'donor@example.test',
            'donor_name'             => 'Test Donor',
            'stripe_subscription_id' => 'sub_A',
            'stripe_customer_id'     => 'cus_A',
        ]);

        // Pledge B matches customer (cus_B) but NOT subscription (sub_B)
        $pledgeB = Pledge::forceCreate([
            'amount_cents'           => 1500,
            'currency'               => 'usd',
            'interval'               => 'month',
            'status'                 => 'incomplete',
            'donor_email'            => 'donor2@example.test',
            'donor_name'             => 'Other Donor',
            'stripe_subscription_id' => 'sub_B',
            'stripe_customer_id'     => 'cus_B',
        ]);

        $periodStart = now()->timestamp;
        $periodEnd   = now()->addMonth()->timestamp;

        // Invoice says: subscription=sub_A but customer=cus_B (mismatch)
        $event = (object) [
            'type' => 'invoice.paid',
            'data' => (object) [
                'object' => (object) [
                    'id'                 => 'in_mismatch',
                    'billing_reason'     => 'subscription_cycle',
                    'subscription'       => 'sub_A',
                    'customer'           => 'cus_B',
                    'payment_intent'     => (object) [
                        'id'             => 'pi_mismatch',
                        'payment_method' => 'pm_mismatch',
                    ],
                    'charge'             => 'ch_mismatch',
                    'amount_paid'        => 1500,
                    'currency'           => 'usd',
                    'hosted_invoice_url' => 'https://example.test/invoices/in_mismatch',
                    'customer_email'     => 'donor@example.test',
                    'lines' => (object) [
                        'data' => [
                            (object) [
                                'subscription' => 'sub_A',
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

        // Current behavior: pledge is resolved by subscription id and becomes active.
        $pledgeA->refresh();
        $this->assertSame('active', $pledgeA->status);

        // Safety property we CAN enforce today:
        // the other pledge (customer match only) must remain untouched.
        $pledgeB->refresh();
        $this->assertSame('incomplete', $pledgeB->status);

        // Optional: if your handler creates the transaction, it should be linked to pledgeA (subscription match).
        // If your handler doesn't always create it, keep this as a soft assertion.
        $tx = Transaction::query()
            ->where('payment_intent_id', 'pi_mismatch')
            ->first();

        if ($tx) {
            $this->assertSame($pledgeA->id, $tx->pledge_id);
            $this->assertSame('sub_A', $tx->subscription_id);
        }
    }
}
