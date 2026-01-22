<?php

namespace Tests\Feature\Stripe;

use App\Models\Pledge;
use App\Models\Transaction;
use App\Services\StripeService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Stripe\Invoice;
use Stripe\StripeClient;
use Stripe\Subscription;
use Tests\TestCase;

class ExpandedInvoiceFallbackTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_retrieve_and_use_an_expanded_invoice_when_subscription_expansions_are_missing_payment_intent_and_periods(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-02 00:00:00'));

        $pledge = Pledge::factory()->create([
            'status'                   => 'active',
            'currency'                 => 'usd',
            'interval'                 => 'month',
            'amount_cents'             => 200,
            'stripe_customer_id'       => 'cus_EXPECTED',
            'stripe_subscription_id'   => 'sub_test_456',
            'latest_payment_intent_id' => null,
            'latest_invoice_id'        => null,
        ]);

        // Subscription has a latest_invoice id string only (no object), and no usable period fields.
        $subscription = Subscription::constructFrom([
            'id'            => 'sub_test_456',
            'status'        => 'active',
            'customer'      => 'cus_EXPECTED',
            'latest_invoice'=> 'in_missing_expansion',
            'items' => [
                'data' => [
                    [
                        'id' => 'si_test_1',
                        // intentionally omit current_period_* to force invoice fallback
                        'price' => ['id' => 'price_test_200', 'product' => 'prod_test_200'],
                    ],
                ],
            ],
        ]);

        // Expanded invoice provides: PI id, paid_at, line period start/end, amount_paid.
        $expandedInvoice = Invoice::constructFrom([
            'id' => 'in_missing_expansion',
            'paid' => true,
            'status_transitions' => ['paid_at' => Carbon::now()->timestamp],
            'payment_intent' => 'pi_FROM_INVOICE',
            'charge' => 'ch_FROM_INVOICE',
            'amount_paid' => 200,
            'currency' => 'usd',
            'customer' => 'cus_EXPECTED',
            'lines' => [
                'data' => [
                    [
                        'period' => [
                            'start' => Carbon::now()->subDay()->timestamp,
                            'end'   => Carbon::now()->addMonth()->timestamp,
                        ],
                    ],
                ],
            ],
        ]);

        $stripe = Mockery::mock(StripeClient::class);

        // IMPORTANT: StripeClient uses getService() to resolve "subscriptions", "invoices", "paymentIntents", etc.
        $subsService = Mockery::mock();
        $invService  = Mockery::mock();
        $piService   = Mockery::mock();

        $stripe->shouldReceive('getService')->with('subscriptions')->andReturn($subsService);
        $stripe->shouldReceive('getService')->with('invoices')->andReturn($invService);
        $stripe->shouldReceive('getService')->with('paymentIntents')->andReturn($piService);

        // subscriptions->retrieve(...)
        $subsService->shouldReceive('retrieve')->once()->andReturn($subscription);

        // invoices->retrieve(...) – your code tries an "expanded" retrieve first; we succeed on that.
        $invService
            ->shouldReceive('retrieve')
            ->with('in_missing_expansion', Mockery::type('array'))
            ->once()
            ->andReturn($expandedInvoice);

        // Owner-guard: because stripe->paymentIntents exists, the guard may attempt retrieve().
        // Make it throw so the guard "soft-allows" (catch Throwable) and doesn't fail the test.
        $piService
            ->shouldReceive('retrieve')
            ->with('pi_FROM_INVOICE', [])
            ->zeroOrMoreTimes()
            ->andThrow(new \Exception('offline'));

        $service = new StripeService($stripe);

        $service->createSubscriptionForPledge($pledge->refresh(), 'pm_test_2');

        $pledge->refresh();

        $this->assertSame('in_missing_expansion', $pledge->latest_invoice_id);
        $this->assertSame('pi_FROM_INVOICE', $pledge->latest_payment_intent_id);

        $this->assertNotNull($pledge->current_period_start);
        $this->assertNotNull($pledge->current_period_end);
        $this->assertNotNull($pledge->next_pledge_at);
        $this->assertNotNull($pledge->last_pledge_at);

        $tx = Transaction::query()
            ->where('pledge_id', $pledge->id)
            ->whereIn('type', ['subscription_initial', 'subscription_recurring'])
            ->firstOrFail();

        $this->assertSame('pi_FROM_INVOICE', $tx->payment_intent_id);
        $this->assertSame('ch_FROM_INVOICE', $tx->charge_id);
        $this->assertSame('succeeded', $tx->status);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
