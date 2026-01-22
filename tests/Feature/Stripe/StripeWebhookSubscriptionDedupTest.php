<?php

namespace Tests\Feature\Stripe;

use App\Http\Controllers\StripeWebhookController;
use App\Models\Pledge;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StripeWebhookSubscriptionDedupTest extends TestCase
{
    use RefreshDatabase;

    protected StripeWebhookController $ctrl;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.stripe.debug_state', false);

        $this->ctrl = app(StripeWebhookController::class);
    }

    #[Test]
    public function invoice_paid_enriches_existing_subscription_placeholder_and_sets_invoice_column(): void
    {
        $pledge = Pledge::factory()->create([
            'attempt_id'             => 'attempt_1',
            'status'                 => 'pending',
            'stripe_customer_id'     => 'cus_test_1',
            'stripe_subscription_id' => 'sub_test_1',
            'donor_email'            => 'ryan@example.com',
            'donor_name'             => 'Ryan Miller',
            'amount_cents'           => 5000,
            'currency'               => 'usd',
        ]);

        $tx = Transaction::factory()->create([
            'pledge_id'         => $pledge->id,
            'attempt_id'        => $pledge->attempt_id,
            'type'              => 'subscription_initial',
            'status'            => 'pending',
            'source'            => 'donation_widget',
            'subscription_id'   => 'sub_test_1',
            'customer_id'       => 'cus_test_1',
            'payment_intent_id' => null,
            'charge_id'         => null,
            'stripe_invoice_id' => 'in_test_1',
            'amount_cents'      => 5000,
            'currency'          => 'usd',
        ]);

        $this->dispatchWebhook('invoice.paid', $this->fakeInvoice([
            'id'            => 'in_test_1',
            'customer'      => 'cus_test_1',
            'subscription'  => 'sub_test_1',
            'billing_reason'=> 'subscription_create',
            'payment_intent'=> 'pi_test_1',
            'charge'        => 'ch_test_1',
            'amount_paid'   => 5000,
            'currency'      => 'usd',
        ]));

        $this->assertSame(1, Transaction::count());

        $tx->refresh();

        $this->assertSame('subscription_initial', $tx->type);
        $this->assertSame('succeeded', $tx->status);
        $this->assertSame('stripe_webhook', $tx->source);

        $this->assertSame('in_test_1', $tx->stripe_invoice_id);
        $this->assertSame('pi_test_1', $tx->payment_intent_id);
        $this->assertSame('ch_test_1', $tx->charge_id);

        $this->assertNotNull($tx->paid_at);

        $this->assertSame(0, Transaction::where('type', 'one_time')->count());
    }

    #[Test]
    public function duplicate_invoice_paid_events_do_not_create_duplicate_rows(): void
    {
        $pledge = Pledge::factory()->create([
            'attempt_id'             => 'attempt_1',
            'stripe_customer_id'     => 'cus_test_1',
            'stripe_subscription_id' => 'sub_test_1',
            'amount_cents'           => 5000,
            'currency'               => 'usd',
        ]);

        Transaction::factory()->create([
            'pledge_id'         => $pledge->id,
            'attempt_id'        => $pledge->attempt_id,
            'type'              => 'subscription_initial',
            'status'            => 'pending',
            'source'            => 'donation_widget',
            'subscription_id'   => 'sub_test_1',
            'customer_id'       => 'cus_test_1',
            'payment_intent_id' => null,
            'charge_id'         => null,
            'stripe_invoice_id' => 'in_test_1',
            'amount_cents'      => 5000,
            'currency'          => 'usd',
        ]);

        $invoice = $this->fakeInvoice([
            'id'            => 'in_test_1',
            'customer'      => 'cus_test_1',
            'subscription'  => 'sub_test_1',
            'billing_reason'=> 'subscription_create',
            'payment_intent'=> 'pi_test_1',
            'charge'        => 'ch_test_1',
            'amount_paid'   => 5000,
            'currency'      => 'usd',
        ]);

        $this->dispatchWebhook('invoice.paid', $invoice);
        $this->dispatchWebhook('invoice.paid', $invoice); // retry

        $this->assertSame(1, Transaction::count());

        $tx = Transaction::first();
        $this->assertSame('in_test_1', $tx->stripe_invoice_id);
        $this->assertSame('pi_test_1', $tx->payment_intent_id);
        $this->assertSame('ch_test_1', $tx->charge_id);
    }

    #[Test]
    public function new_invoice_creates_new_subscription_recurring_tx_and_does_not_overwrite_previous_invoice_tx(): void
    {
        $pledge = Pledge::factory()->create([
            'attempt_id'             => 'attempt_1',
            'stripe_customer_id'     => 'cus_test_1',
            'stripe_subscription_id' => 'sub_test_1',
            'amount_cents'           => 5000,
            'currency'               => 'usd',
        ]);

        Transaction::factory()->create([
            'pledge_id'         => $pledge->id,
            'attempt_id'        => $pledge->attempt_id,
            'type'              => 'subscription_initial',
            'status'            => 'succeeded',
            'source'            => 'stripe_webhook',
            'subscription_id'   => 'sub_test_1',
            'customer_id'       => 'cus_test_1',
            'payment_intent_id' => 'pi_old',
            'charge_id'         => 'ch_old',
            'stripe_invoice_id' => 'in_old',
            'amount_cents'      => 5000,
            'currency'          => 'usd',
            'paid_at'           => now(),
        ]);

        $this->dispatchWebhook('invoice.paid', $this->fakeInvoice([
            'id'            => 'in_new',
            'customer'      => 'cus_test_1',
            'subscription'  => 'sub_test_1',
            'billing_reason'=> 'subscription_cycle',
            'payment_intent'=> 'pi_new',
            'charge'        => 'ch_new',
            'amount_paid'   => 5000,
            'currency'      => 'usd',
        ]));

        $this->assertSame(2, Transaction::count());

        $newTx = Transaction::where('payment_intent_id', 'pi_new')->first();
        $this->assertNotNull($newTx);
        $this->assertSame('in_new', $newTx->stripe_invoice_id);
        $this->assertSame('ch_new', $newTx->charge_id);
        $this->assertSame('subscription_recurring', $newTx->type);
        $this->assertSame('succeeded', $newTx->status);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    protected function dispatchWebhook(string $type, object $object): void
    {
        $event = (object) [
            'type' => $type,
            'data' => (object) [
                'object' => $object,
            ],
        ];

        $this->ctrl->handleEvent($event);
    }

    protected function fakeInvoice(array $overrides = []): object
    {
        $base = [
            'id' => 'in_test',
            'customer' => 'cus_test',
            'subscription' => 'sub_test',
            'billing_reason' => 'subscription_cycle',
            'payment_intent' => 'pi_test',
            'charge' => 'ch_test',
            'amount_paid' => 5000,
            'amount_due' => 5000,
            'currency' => 'usd',
            'hosted_invoice_url' => 'https://example.com/invoice',
            'charges' => (object) [
                'data' => [
                    (object) [
                        'id' => 'ch_test',
                        'payment_intent' => 'pi_test',
                    ],
                ],
            ],
            'lines' => (object) [
                'data' => [
                    (object) [
                        'period' => (object) [
                            'start' => now()->subMonth()->timestamp,
                            'end' => now()->addMonth()->timestamp,
                        ],
                        'subscription' => 'sub_test',
                    ],
                ],
            ],
            'status_transitions' => (object) [
                'paid_at' => now()->timestamp,
            ],
        ];

        foreach ($overrides as $k => $v) {
            $base[$k] = $v;
        }

        return json_decode(json_encode($base), false);
    }
}
