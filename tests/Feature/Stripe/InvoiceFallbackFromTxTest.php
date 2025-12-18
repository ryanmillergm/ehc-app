<?php

namespace Tests\Feature\Stripe;

use App\Http\Controllers\StripeWebhookController;
use App\Models\Pledge;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InvoiceFallbackFromTxTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function invoice_paid_can_resolve_pledge_via_existing_transaction_when_invoice_missing_customer_and_subscription(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-12-17 12:00:00'));

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
            'current_period_start'   => null,
            'current_period_end'     => null,
            'next_pledge_at'         => null,
            'latest_invoice_id'      => null,
            'latest_payment_intent_id' => null,
            'metadata'               => [],
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
            'receipt_url'       => null,
            'metadata'          => [],
            'paid_at'           => null,
            'created_at'        => Carbon::now()->subSeconds(5),
            'updated_at'        => Carbon::now()->subSeconds(5),
        ]);

        $paidAt = Carbon::now()->timestamp;
        $periodStart = Carbon::now()->timestamp;
        $periodEnd   = Carbon::now()->copy()->addHours(1)->timestamp;

        $invoice = (object) [
            'id' => 'inpay_123',
            // missing customer/subscription on purpose
            'payment_intent'      => 'pi_match',
            'charge'              => 'ch_from_invoice',
            'hosted_invoice_url'  => 'https://example.test/invoice/inpay_123',
            'amount_paid'         => 1000,
            'currency'            => 'usd',
            'paid'                => true,
            'lines' => (object) [
                'data' => [
                    (object) [
                        'period' => (object) [
                            'start' => $periodStart,
                            'end'   => $periodEnd,
                        ],
                    ],
                ],
            ],
            'status_transitions' => (object) [
                'paid_at' => $paidAt,
            ],
        ];

        $event = (object) [
            'type' => 'invoice.payment_succeeded',
            'data' => (object) [
                'object' => $invoice,
            ],
        ];

        app(StripeWebhookController::class)->handleEvent($event);

        $this->assertDatabaseCount('transactions', 1);

        $tx->refresh();

        $this->assertSame('https://example.test/invoice/inpay_123', $tx->receipt_url);
        $this->assertSame('inpay_123', data_get($tx->metadata, 'stripe_invoice_id'));

        $this->assertSame('pi_match', $tx->payment_intent_id);
        $this->assertSame('ch_match', $tx->charge_id);
        $this->assertNotNull($tx->paid_at);
        $this->assertSame(
            Carbon::createFromTimestamp($paidAt)->toDateTimeString(),
            $tx->paid_at->toDateTimeString()
        );

        $pledge->refresh();

        $this->assertSame('active', $pledge->status);
        $this->assertSame('inpay_123', $pledge->latest_invoice_id);
        $this->assertSame('pi_match', $pledge->latest_payment_intent_id);

        $this->assertNotNull($pledge->current_period_start);
        $this->assertNotNull($pledge->current_period_end);
        $this->assertNotNull($pledge->next_pledge_at);

        $this->assertSame(
            Carbon::createFromTimestamp($periodStart)->toDateTimeString(),
            $pledge->current_period_start->toDateTimeString()
        );

        $this->assertSame(
            Carbon::createFromTimestamp($periodEnd)->toDateTimeString(),
            $pledge->current_period_end->toDateTimeString()
        );

        $this->assertSame(
            Carbon::createFromTimestamp($periodEnd)->toDateTimeString(),
            $pledge->next_pledge_at->toDateTimeString()
        );
    }

    #[Test]
    public function invoice_paid_backfills_charge_id_when_transaction_missing_it(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-12-17 12:00:00'));

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
            'metadata'               => [],
        ]);

        $tx = Transaction::forceCreate([
            'user_id'           => null,
            'pledge_id'         => $pledge->id,
            'type'              => 'subscription_recurring',
            'status'            => 'succeeded',
            'payment_intent_id' => 'pi_match',
            'charge_id'         => null,           // <— missing on purpose
            'customer_id'       => 'cus_test',
            'subscription_id'   => null,
            'amount_cents'      => 1000,
            'currency'          => 'usd',
            'receipt_url'       => null,
            'metadata'          => [],
            'paid_at'           => null,
            'created_at'        => Carbon::now()->subSeconds(5),
            'updated_at'        => Carbon::now()->subSeconds(5),
        ]);

        $paidAt      = Carbon::now()->timestamp;
        $periodStart = Carbon::now()->timestamp;
        $periodEnd   = Carbon::now()->copy()->addHours(1)->timestamp;

        $invoice = (object) [
            'id'                 => 'inpay_123',
            'payment_intent'     => 'pi_match',
            'charge'             => 'ch_from_invoice',
            'hosted_invoice_url' => 'https://example.test/invoice/inpay_123',
            'amount_paid'        => 1000,
            'currency'           => 'usd',
            'paid'               => true,
            'lines' => (object) [
                'data' => [
                    (object) [
                        'period' => (object) [
                            'start' => $periodStart,
                            'end'   => $periodEnd,
                        ],
                    ],
                ],
            ],
            'status_transitions' => (object) [
                'paid_at' => $paidAt,
            ],
        ];

        $event = (object) [
            'type' => 'invoice.payment_succeeded',
            'data' => (object) ['object' => $invoice],
        ];

        app(StripeWebhookController::class)->handleEvent($event);

        $tx->refresh();

        $this->assertSame('ch_from_invoice', $tx->charge_id);
        $this->assertNotNull($tx->paid_at);
    }
}
