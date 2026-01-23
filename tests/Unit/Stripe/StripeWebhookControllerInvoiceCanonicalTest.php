<?php

namespace Tests\Unit\Stripe;

use App\Models\Pledge;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StripeWebhookControllerInvoiceCanonicalTest extends TestCase
{
    use RefreshDatabase;

    private function controller(): object
    {
        return new class extends \App\Http\Controllers\StripeWebhookController {
            public function callHandlePaymentIntentSucceeded(object $pi): void
            {
                $this->handlePaymentIntentSucceeded($pi);
            }

            public function callHandleInvoicePaid(object $invoice, string $eventType = 'invoice.paid'): void
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
        bool $paid = true,
        string $billingReason = 'subscription_create'
    ): object {
        return (object) [
            'id'               => $invoiceId,
            'status'           => $paid ? 'paid' : 'open',
            'paid'             => $paid,
            'billing_reason'   => $billingReason,
            'customer'         => $customerId,
            'subscription'     => $subscriptionId,
            'payment_intent'   => $paymentIntentId, // can be null (common in real life)
            'currency'         => 'usd',
            'amount_due'       => 1676,
            'amount_paid'      => $paid ? 1676 : 0,
            'amount_remaining' => $paid ? 0 : 1676,
            'hosted_invoice_url' => "https://example.test/invoice/{$invoiceId}",
            'status_transitions' => (object) [
                'paid_at' => $paid ? time() : null,
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
        $attemptId = (string) \Str::uuid();

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

        $this->assertSame($before, Transaction::count(), 'PI succeeded w/ invoice must not create new tx rows.');
    }

    #[Test]
    public function invoice_paid_updates_canonical_invoice_row_and_does_not_assign_invoice_id_to_metadata_only_row(): void
    {
        $controller = $this->controller();

        $user = User::factory()->create();

        // IMPORTANT: match real preconditions before invoice.paid hits (per your logs)
        $pledge = Pledge::factory()->create([
            'user_id'               => $user->id,
            'status'                => 'active',
            'stripe_customer_id'    => 'cus_123',
            'stripe_subscription_id'=> 'sub_123',
            'latest_invoice_id'     => 'in_123',
            'latest_payment_intent_id' => 'pi_123',
        ]);

        $attemptId = (string) \Str::uuid();

        // Fallback widget row (used by invoice handler to locate pledge/user/attempt in some paths).
        Transaction::factory()->create([
            'user_id'   => $user->id,
            'pledge_id' => $pledge->id,
            'type'      => 'subscription_initial',
            'status'    => 'pending',
            'metadata'  => [
                'stage'      => 'subscription_creation',
                'attempt_id' => $attemptId,
            ],
        ]);

        // Canonical owner row — in prod this often already has PI/charge/customer/subscription populated
        // by the time invoice.paid arrives.
        $canonical = Transaction::factory()->create([
            'user_id'           => $user->id,
            'pledge_id'         => $pledge->id,
            'stripe_invoice_id' => 'in_123',
            'subscription_id'   => 'sub_123',
            'customer_id'       => 'cus_123',
            'payment_intent_id' => 'pi_123',  // key: already owned here
            'charge_id'         => 'ch_123',  // key: already known from earlier events/sync
            'type'              => 'subscription_initial',
            'status'            => 'pending',
            'metadata'          => [],
        ]);

        // Wrong row that only has invoice id in metadata (this is the prod-crash pattern).
        $metadataOnly = Transaction::factory()->create([
            'user_id'           => $user->id,
            'pledge_id'         => $pledge->id,
            'payment_intent_id' => 'pi_999', // must be unique in DB
            'type'              => 'one_time',
            'status'            => 'succeeded',
            'stripe_invoice_id' => null,
            'metadata'          => [
                'stripe_invoice_id' => 'in_123',
            ],
        ]);

        // Realistic invoice payload: PI can be null on invoice events (per your logs).
        $invoice = $this->makeInvoice(
            invoiceId: 'in_123',
            subscriptionId: 'sub_123',
            customerId: 'cus_123',
            attemptId: $attemptId,
            user: $user,
            pledge: $pledge,
            paymentIntentId: null,
            paid: true,
        );

        $beforeCount = Transaction::count();

        $controller->callHandleInvoicePaid($invoice, 'invoice.paid');

        $this->assertSame($beforeCount, Transaction::count(), 'Invoice event must not create new tx rows when canonical exists.');

        $canonical->refresh();
        $metadataOnly->refresh();

        // Canonical row should be marked paid.
        $this->assertSame('in_123', $canonical->stripe_invoice_id);
        $this->assertSame('succeeded', $canonical->status);
        $this->assertNotNull($canonical->paid_at);

        // Metadata-only row must not be given stripe_invoice_id (would violate unique pledge+invoice).
        $this->assertNull($metadataOnly->stripe_invoice_id);
    }

    #[Test]
    public function invoice_events_are_idempotent_and_never_create_duplicates_even_if_payment_intent_is_present(): void
    {
        $controller = $this->controller();

        $user = User::factory()->create();
        $pledge = Pledge::factory()->create([
            'user_id'               => $user->id,
            'status'                => 'active',
            'stripe_customer_id'    => 'cus_123',
            'stripe_subscription_id'=> 'sub_123',
            'latest_invoice_id'     => 'in_123',
            'latest_payment_intent_id' => 'pi_123',
        ]);

        $attemptId = (string) \Str::uuid();

        // Canonical tx exists.
        $canonical = Transaction::factory()->create([
            'user_id'           => $user->id,
            'pledge_id'         => $pledge->id,
            'stripe_invoice_id' => 'in_123',
            'subscription_id'   => 'sub_123',
            'customer_id'       => 'cus_123',
            'payment_intent_id' => 'pi_123',
            'charge_id'         => 'ch_123',
            'type'              => 'subscription_initial',
            'status'            => 'pending',
            'metadata'          => [],
        ]);

        $invoiceWithPi = $this->makeInvoice(
            invoiceId: 'in_123',
            subscriptionId: 'sub_123',
            customerId: 'cus_123',
            attemptId: $attemptId,
            user: $user,
            pledge: $pledge,
            paymentIntentId: 'pi_123',
            paid: true,
        );

        $beforeCount = Transaction::count();

        $controller->callHandleInvoicePaid($invoiceWithPi, 'invoice.paid');
        $controller->callHandleInvoicePaid($invoiceWithPi, 'invoice.payment_succeeded');

        $this->assertSame($beforeCount, Transaction::count(), 'Repeated invoice events must not create new tx rows.');

        $this->assertSame(
            1,
            Transaction::query()
                ->where('pledge_id', $pledge->id)
                ->where('stripe_invoice_id', 'in_123')
                ->count(),
            'Exactly one canonical transaction row must exist for pledge+invoice.'
        );

        $canonical->refresh();
        $this->assertSame('succeeded', $canonical->status);
        $this->assertNotNull($canonical->paid_at);
    }
}
