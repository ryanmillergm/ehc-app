<?php

namespace Tests\Feature\Stripe;

use App\Http\Controllers\StripeWebhookController;
use App\Models\Pledge;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AssertsStripeWebhookState;
use Tests\TestCase;

class StripeWebhookIdempotencyTest extends TestCase
{
    use RefreshDatabase;
    use AssertsStripeWebhookState;

    public function test_invoice_paid_is_idempotent_and_does_not_create_duplicates(): void
    {
        $pledge = Pledge::forceCreate([
            'user_id'                => null,
            'amount_cents'           => 1500,
            'currency'               => 'usd',
            'interval'               => 'month',
            'status'                 => 'incomplete',
            'donor_email'            => 'donor@example.test',
            'donor_name'             => 'Test Donor',
            'stripe_subscription_id' => 'sub_idem',
            'stripe_customer_id'     => 'cus_idem',
        ]);

        $controller = new StripeWebhookController();

        $periodStart = now()->timestamp;
        $periodEnd   = now()->addMonth()->timestamp;

        $event = (object) [
            'type' => 'invoice.paid',
            'data' => (object) [
                'object' => (object) [
                    'id'                 => 'in_idem',
                    'billing_reason'     => 'subscription_cycle',
                    'subscription'       => 'sub_idem',
                    'customer'           => 'cus_idem',
                    'customer_email'     => 'donor@example.test',
                    'status'             => 'paid',
                    'amount_paid'        => 1500,
                    'currency'           => 'usd',
                    'hosted_invoice_url' => 'https://example.test/invoices/in_idem',
                    'payment_intent'     => (object) [
                        'id'             => 'pi_idem',
                        'payment_method' => 'pm_idem',
                    ],
                    'charge' => 'ch_idem',
                    'lines' => (object) [
                        'data' => [
                            (object) [
                                'subscription' => 'sub_idem',
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

        // deliver twice
        $controller->handleEvent($event);
        $controller->handleEvent($event);

        $this->assertSame(1, Transaction::count());

        $tx = Transaction::firstOrFail();

        $this->assertTxUniquenessByPi('pi_idem');
        $this->assertTxUniquenessByInvoice('in_idem');

        $this->assertSubscriptionTxComplete($tx, [
            'subscription_id'   => 'sub_idem',
            'stripe_invoice_id' => 'in_idem',
            'payment_intent_id' => 'pi_idem',
            'charge_id'         => 'ch_idem',
            'customer_id'       => 'cus_idem',
        ]);

        $this->assertPledgeActiveAndUpdated($pledge, [
            'status'                   => 'active',
            'latest_invoice_id'        => 'in_idem',
            'latest_payment_intent_id' => 'pi_idem',
        ]);
    }

    public function test_one_time_charge_succeeded_is_idempotent(): void
    {
        // Placeholder created by widget/start flow
        $tx = Transaction::factory()->create([
            'type'              => 'one_time',
            'status'            => 'pending',
            'source'            => 'donation_widget',
            'payment_intent_id' => 'pi_one_idem',
            'charge_id'         => null,
            'customer_id'       => null,
            'payment_method_id' => null,
            'amount_cents'      => 100,
            'currency'          => 'usd',
            'receipt_url'       => null,
            'metadata'          => ['donation' => 'widget'],
            'paid_at'           => null,
        ]);

        $charge = (object) [
            'id'             => 'ch_one_idem',
            'invoice'        => null,
            'payment_intent' => 'pi_one_idem',
            'customer'       => 'cus_one_idem',
            'payment_method' => 'pm_one_idem',
            'amount'         => 100,
            'currency'       => 'usd',
            'receipt_url'    => 'https://example.test/receipt/ch_one_idem',
            'billing_details' => (object) [
                'email' => 'donor@example.test',
                'name'  => 'Test Donor',
            ],
            'payment_method_details' => (object) [
                'card' => (object) [
                    'brand' => 'visa',
                    'last4' => '4242',
                ],
            ],
        ];

        $controller = new class extends StripeWebhookController {
            public function callHandleChargeSucceeded(object $charge): void
            {
                $this->handleChargeSucceeded($charge);
            }
        };

        // Deliver twice (duplicate webhook)
        $controller->callHandleChargeSucceeded($charge);
        $controller->callHandleChargeSucceeded($charge);

        // No duplicates
        $this->assertSame(1, Transaction::count());

        $tx->refresh();

        // Still the same row, enriched
        $this->assertSame('one_time', $tx->type);
        $this->assertSame('pi_one_idem', $tx->payment_intent_id);

        $this->assertSame('ch_one_idem', $tx->charge_id);
        $this->assertSame('cus_one_idem', $tx->customer_id);
        $this->assertSame('pm_one_idem', $tx->payment_method_id);
        $this->assertSame('https://example.test/receipt/ch_one_idem', $tx->receipt_url);

        // Handler does enrichment here; status finalization may be handled elsewhere
        $this->assertSame('pending', $tx->status);

        // Card metadata preserved/merged
        $this->assertSame('visa', data_get($tx->metadata, 'card_brand'));
        $this->assertSame('4242', data_get($tx->metadata, 'card_last4'));
        $this->assertSame('widget', data_get($tx->metadata, 'donation'));

        // Uniqueness by PI still enforced
        $this->assertTxUniquenessByPi('pi_one_idem');
    }
}
