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
        $controller = new StripeWebhookController();

        $event = (object) [
            'type' => 'charge.succeeded',
            'data' => (object) [
                'object' => (object) [
                    'id'            => 'ch_one_idem',
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
                ],
            ],
        ];

        $controller->handleEvent($event);
        $controller->handleEvent($event);

        $this->assertSame(1, Transaction::count());

        $tx = Transaction::firstOrFail();
        $this->assertSame('one_time', $tx->type);
        $this->assertSame('succeeded', $tx->status);
        $this->assertSame('pi_one_idem', $tx->payment_intent_id);

        $this->assertTxUniquenessByPi('pi_one_idem');
    }
}
