<?php

namespace Tests\Feature\Stripe;

use App\Http\Controllers\StripeWebhookController;
use App\Models\Pledge;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AssertsStripeWebhookState;
use Tests\TestCase;

class StripeWebhookDataCompletenessTest extends TestCase
{
    use RefreshDatabase;
    use AssertsStripeWebhookState;

    public function test_subscription_invoice_paid_results_in_complete_tx_and_complete_pledge_fields(): void
    {
        $pledge = Pledge::forceCreate([
            'user_id'                => null,
            'amount_cents'           => 1500,
            'currency'               => 'usd',
            'interval'               => 'month',
            'status'                 => 'incomplete',
            'donor_email'            => 'donor@example.test',
            'donor_name'             => 'Test Donor',
            'stripe_subscription_id' => 'sub_complete',
            'stripe_customer_id'     => 'cus_complete',
        ]);

        $periodStart = now()->timestamp;
        $periodEnd   = now()->addMonth()->timestamp;

        $event = (object) [
            'type' => 'invoice.paid',
            'data' => (object) [
                'object' => (object) [
                    'id'                 => 'in_complete',
                    'billing_reason'     => 'subscription_cycle',
                    'subscription'       => 'sub_complete',
                    'customer'           => 'cus_complete',
                    'customer_email'     => 'donor@example.test',
                    'status'             => 'paid',
                    'amount_paid'        => 1500,
                    'currency'           => 'usd',
                    'hosted_invoice_url' => 'https://example.test/invoices/in_complete',
                    'payment_intent'     => (object) [
                        'id'             => 'pi_complete',
                        'payment_method' => 'pm_complete',
                    ],
                    'charge' => 'ch_complete',
                    'status_transitions' => (object) [
                        'paid_at' => now()->timestamp,
                    ],
                    'lines' => (object) [
                        'data' => [
                            (object) [
                                'subscription' => 'sub_complete',
                                'period' => (object) ['start' => $periodStart, 'end' => $periodEnd],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        (new StripeWebhookController())->handleEvent($event);

        $tx = Transaction::where('payment_intent_id', 'pi_complete')->firstOrFail();

        $this->assertSubscriptionTxComplete($tx, [
            'subscription_id'   => 'sub_complete',
            'customer_id'       => 'cus_complete',
            'payment_method_id' => 'pm_complete',
            'charge_id'         => 'ch_complete',
        ]);

        $this->assertPledgeActiveAndSynced($pledge, [
            'latest_invoice_id'        => 'in_complete',
            'latest_payment_intent_id' => 'pi_complete',
        ]);
    }

    public function test_one_time_charge_succeeded_results_in_complete_tx_fields(): void
    {
        $event = (object) [
            'type' => 'charge.succeeded',
            'data' => (object) [
                'object' => (object) [
                    'id'            => 'ch_one_complete',
                    'invoice'        => null,
                    'payment_intent' => 'pi_one_complete',
                    'customer'       => 'cus_one_complete',
                    'payment_method' => 'pm_one_complete',
                    'amount'         => 777,
                    'currency'       => 'usd',
                    'receipt_url'    => 'https://example.test/receipt/ch_one_complete',
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

        $tx = Transaction::where('payment_intent_id', 'pi_one_complete')->firstOrFail();

        $this->assertSame('one_time', $tx->type);
        $this->assertSame('succeeded', $tx->status);
        $this->assertSame('ch_one_complete', $tx->charge_id);
        $this->assertSame('cus_one_complete', $tx->customer_id);
        $this->assertSame('pm_one_complete', $tx->payment_method_id);
        $this->assertSame(777, $tx->amount_cents);
        $this->assertSame('usd', $tx->currency);
        $this->assertSame('stripe_webhook', $tx->source);
        $this->assertNotNull($tx->paid_at);

        $meta = $this->meta($tx->metadata);
        $this->assertSame('visa', $meta['card_brand'] ?? null);
        $this->assertSame('4242', $meta['card_last4'] ?? null);
        $this->assertSame('US', $meta['card_country'] ?? null);
        $this->assertSame('credit', $meta['card_funding'] ?? null);
    }
}
