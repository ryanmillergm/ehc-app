<?php

namespace Tests\Unit\Stripe;

use App\Models\Pledge;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StripeWebhookControllerInvoiceCanonicalTest2 extends TestCase
{
    use RefreshDatabase;

    private function controller(): object
    {
        return new class extends \App\Http\Controllers\StripeWebhookController {
            public function callHandlePaymentIntentSucceeded(object $pi): void
            {
                $this->handlePaymentIntentSucceeded($pi);
            }

            public function callHandleInvoicePaid(object $invoice, string $eventType = 'invoice.payment_succeeded'): void
            {
                $this->handleInvoicePaid($invoice, $eventType);
            }
        };
    }

    private function makeInvoice(
        string $invoiceId,
        string $subscriptionId,
        string $customerId,
        string $attemptId,
        User $user,
        Pledge $pledge,
        ?string $paymentIntentId = null,
    ): object {
        return (object) [
            'id'                 => $invoiceId,
            'status'             => 'paid',
            'billing_reason'     => 'subscription_create',
            'customer'           => $customerId,
            'subscription'       => $subscriptionId,
            // Important: in real life this is sometimes null on the invoice event
            'payment_intent'     => $paymentIntentId,
            'hosted_invoice_url' => "https://example.test/invoice/{$invoiceId}",
            'status_transitions' => (object) [
                'paid_at' => time(),
            ],
            'lines' => (object) [
                'data' => [
                    (object) [
                        'metadata' => [
                            'attempt_id' => $attemptId,
                            'user_id'    => (string) $user->id,
                            'pledge_id'  => (string) $pledge->id,
                            'source'     => 'donation_widget',
                        ],
                    ],
                ],
            ],
        ];
    }

    #[Test]
    public function payment_intent_succeeded_with_invoice_is_ignored_to_prevent_duplicate_rows(): void
    {
        $controller = $this->controller();

        $pledge = Pledge::factory()->create();
        $attemptId = (string) Str::uuid();

        Transaction::factory()->create([
            'pledge_id' => $pledge->id,
            'type'      => 'subscription_initial',
            'status'    => 'pending',
            'metadata'  => [
                'stage'      => 'subscription_creation',
                'attempt_id' => $attemptId,
            ],
        ]);

        $pi = (object) [
            'id'      => 'pi_123',
            'invoice' => 'in_123',
        ];

        $before = Transaction::count();

        $controller->callHandlePaymentIntentSucceeded($pi);

        $this->assertSame($before, Transaction::count(), 'PI succeeded linked to an invoice must not create a new tx in subscription flows.');
    }

    #[Test]
    public function invoice_payment_succeeded_adopts_existing_payment_intent_row_and_does_not_create_a_third_transaction(): void
    {
        $controller = $this->controller();

        $user = User::factory()->create();
        $pledge = Pledge::factory()->create(['user_id' => $user->id]);

        $attemptId = (string) Str::uuid();

        // 1) Placeholder created by widget at subscription creation time
        $placeholder = Transaction::factory()->create([
            'user_id'   => $user->id,
            'pledge_id' => $pledge->id,
            'attempt_id'=> $attemptId,
            'type'      => 'subscription_initial',
            'status'    => 'pending',
            'source'    => 'donation_widget',
            'metadata'  => [
                'stage'      => 'subscription_creation',
                'attempt_id' => $attemptId,
            ],
        ]);

        // 2) A second tx already exists that owns the PaymentIntent + Charge
        //    (this is the “id=9” shape you showed from prod)
        $piOwner = Transaction::factory()->create([
            'user_id'           => $user->id,
            'pledge_id'         => $pledge->id,
            'attempt_id'        => $attemptId,
            'payment_intent_id' => 'pi_123',
            'charge_id'         => 'ch_123',
            'subscription_id'   => 'sub_123',
            'stripe_invoice_id' => null, // not set yet
            'type'              => 'one_time',
            'status'            => 'succeeded',
            'source'            => 'stripe_webhook',
            'metadata'          => [
                'attempt_id' => $attemptId,
                // sometimes you also had invoice id stuffed in metadata
                'stripe_invoice_id' => 'in_123',
            ],
        ]);

        $invoice = $this->makeInvoice(
            invoiceId: 'in_123',
            subscriptionId: 'sub_123',
            customerId: 'cus_123',
            attemptId: $attemptId,
            user: $user,
            pledge: $pledge,
            paymentIntentId: null, // simulate real webhook where invoice.payment_succeeded has null PI
        );

        $beforeCount = Transaction::count();

        $controller->callHandleInvoicePaid($invoice, 'invoice.payment_succeeded');

        // Key guarantee: no new third row created
        $this->assertSame($beforeCount, Transaction::count(), 'Invoice webhook must not create a new tx when a tx already owns the PaymentIntent.');

        // Exactly one canonical invoice row for this pledge+invoice
        $this->assertSame(
            1,
            Transaction::query()
                ->where('pledge_id', $pledge->id)
                ->where('stripe_invoice_id', 'in_123')
                ->count(),
            'There must be exactly one canonical tx row for (pledge_id, stripe_invoice_id).'
        );

        // The PI owner row should become (or remain) the canonical invoice row
        $piOwner->refresh();
        $this->assertSame('in_123', $piOwner->stripe_invoice_id, 'The tx that owns the PI should be the invoice owner (prevents split-brain rows).');

        // Placeholder must NOT also become an invoice owner (that creates your 3-row mess)
        $placeholder->refresh();
        $this->assertNotSame('in_123', $placeholder->stripe_invoice_id);
    }

    #[Test]
    public function invoice_payment_succeeded_is_idempotent_and_does_not_duplicate_canonical_invoice_rows(): void
    {
        $controller = $this->controller();

        $user = User::factory()->create();
        $pledge = Pledge::factory()->create(['user_id' => $user->id]);
        $attemptId = (string) Str::uuid();

        $piOwner = Transaction::factory()->create([
            'user_id'           => $user->id,
            'pledge_id'         => $pledge->id,
            'attempt_id'        => $attemptId,
            'payment_intent_id' => 'pi_123',
            'charge_id'         => 'ch_123',
            'subscription_id'   => 'sub_123',
            'stripe_invoice_id' => null,
            'type'              => 'one_time',
            'status'            => 'succeeded',
            'source'            => 'stripe_webhook',
            'metadata'          => [
                'attempt_id' => $attemptId,
            ],
        ]);

        $invoice = $this->makeInvoice(
            invoiceId: 'in_123',
            subscriptionId: 'sub_123',
            customerId: 'cus_123',
            attemptId: $attemptId,
            user: $user,
            pledge: $pledge,
            paymentIntentId: null
        );

        $beforeCount = Transaction::count();

        $controller->callHandleInvoicePaid($invoice, 'invoice.payment_succeeded');
        $controller->callHandleInvoicePaid($invoice, 'invoice.payment_succeeded');

        $this->assertSame($beforeCount, Transaction::count(), 'Replaying the same invoice event must not create rows.');

        $this->assertSame(
            1,
            Transaction::query()
                ->where('pledge_id', $pledge->id)
                ->where('stripe_invoice_id', 'in_123')
                ->count(),
            'Replaying invoice events must not create duplicate canonical invoice rows.'
        );

        $piOwner->refresh();
        $this->assertSame('in_123', $piOwner->stripe_invoice_id);
    }
}
