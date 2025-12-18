<?php

namespace Tests\Feature\Stripe;

use App\Http\Controllers\StripeWebhookController;
use App\Models\Pledge;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InvoicePaidPopulatesFieldsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function invoice_payment_succeeded_populates_missing_pledge_and_transaction_fields_for_monthly_subscription(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-12-18 00:44:00'));

        $user = User::factory()->create();

        $pledge = Pledge::forceCreate([
            'user_id'                => $user->id,
            'attempt_id'             => 'attempt_123',
            'amount_cents'           => 502,
            'currency'               => 'usd',
            'interval'               => 'month',
            'status'                 => 'active',
            'stripe_customer_id'     => 'cus_test',
            'stripe_subscription_id' => 'sub_test',
            'stripe_price_id'        => 'price_test',
            'setup_intent_id'        => 'seti_test',
            'donor_email'            => $user->email,
            'donor_name'             => 'Ryan Miller',
            // intentionally missing
            'current_period_start'   => null,
            'current_period_end'     => null,
            'next_pledge_at'         => null,
            'latest_payment_intent_id' => null,
            'metadata'               => ['frequency' => 'monthly'],
        ]);

        $tx = Transaction::forceCreate([
            'user_id'         => $user->id,
            'pledge_id'       => $pledge->id,
            'attempt_id'      => 'attempt_123',
            'type'            => 'subscription_initial',
            'status'          => 'pending',
            'customer_id'     => 'cus_test',
            'subscription_id' => 'sub_test',
            'payment_intent_id' => null,   // missing on purpose
            'charge_id'         => null,   // missing on purpose
            'amount_cents'    => 502,
            'currency'        => 'usd',
            'receipt_url'     => null,
            'paid_at'         => null,
            'metadata'        => [
                'stage' => 'subscription_creation',
            ],
            'created_at'      => Carbon::now()->subSeconds(10),
            'updated_at'      => Carbon::now()->subSeconds(10),
        ]);

        $paidAt      = Carbon::now()->timestamp;
        $periodStart = Carbon::now()->timestamp;
        $periodEnd   = Carbon::now()->copy()->addMonthNoOverflow()->timestamp;

        $invoice = (object) [
            'id'            => 'in_test',
            'customer'      => 'cus_test',
            'subscription'  => 'sub_test',
            'payment_intent'=> 'pi_test',
            'charge'        => 'ch_test',
            'hosted_invoice_url' => 'https://example.test/invoice/in_test',
            'paid'          => true,
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

        $pledge->refresh();
        $tx->refresh();

        $this->assertSame('active', $pledge->status);
        $this->assertSame('in_test', $pledge->latest_invoice_id);
        $this->assertSame('pi_test', $pledge->latest_payment_intent_id);

        $this->assertNotNull($pledge->current_period_start);
        $this->assertNotNull($pledge->current_period_end);
        $this->assertNotNull($pledge->next_pledge_at);

        $this->assertSame(Carbon::createFromTimestamp($periodStart)->toDateTimeString(), $pledge->current_period_start->toDateTimeString());
        $this->assertSame(Carbon::createFromTimestamp($periodEnd)->toDateTimeString(), $pledge->current_period_end->toDateTimeString());
        $this->assertSame(Carbon::createFromTimestamp($periodEnd)->toDateTimeString(), $pledge->next_pledge_at->toDateTimeString());

        $this->assertSame('succeeded', $tx->status);
        $this->assertSame('pi_test', $tx->payment_intent_id);
        $this->assertSame('ch_test', $tx->charge_id);
        $this->assertSame('https://example.test/invoice/in_test', $tx->receipt_url);
        $this->assertNotNull($tx->paid_at);
        $this->assertSame('in_test', data_get($tx->metadata, 'stripe_invoice_id'));
    }
}
