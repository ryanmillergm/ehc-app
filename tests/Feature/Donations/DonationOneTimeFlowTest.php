<?php

namespace Tests\Feature\Donations;

use App\Models\Transaction;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Stripe\PaymentIntent;
use Tests\TestCase;

class DonationOneTimeFlowTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function start_creates_transaction_and_returns_client_secret(): void
    {
        $this->mock(StripeService::class, function ($mock) {
            $mock->shouldReceive('createOneTimePaymentIntent')
                ->once()
                ->andReturn($this->fakeStripePaymentIntent('pi_test_1', 'requires_payment_method'));
        });

        $res = $this->postJson(route('donations.start'), [
            'frequency' => 'one_time',
            'amount'    => 200,     // controller expects "amount" in dollars
            'currency'  => 'USD',
            'donor'     => [
                'email' => 'ryan@example.com',
                'name'  => 'Ryan Miller',
            ],
        ]);

        $res->assertOk();

        $data = $res->json();

        $clientSecret = $data['clientSecret'] ?? $data['client_secret'] ?? null;
        $piId         = $data['paymentIntentId'] ?? $data['payment_intent_id'] ?? null;
        $txId         = $data['transactionId'] ?? $data['transaction_id'] ?? null;
        $attemptId    = $data['attemptId'] ?? $data['attempt_id'] ?? null;

        $this->assertNotEmpty($clientSecret);
        $this->assertSame('pi_test_1', $piId);
        $this->assertNotEmpty($attemptId);
        $this->assertNotEmpty($txId);

        $this->assertDatabaseCount('transactions', 1);

        $tx = Transaction::firstOrFail();

        $this->assertSame('one_time', $tx->type);
        $this->assertSame('donation_widget', $tx->source);

        // request "amount" is dollars; controller converts to cents
        $this->assertSame(20000, (int) $tx->amount_cents);
        $this->assertSame('usd', strtolower((string) $tx->currency));

        // IMPORTANT: controller should persist PI id to tx (see fix below)
        $this->assertSame('pi_test_1', $tx->payment_intent_id);
    }

    #[Test]
    public function complete_fails_when_payment_intent_not_succeeded(): void
    {
        $tx = Transaction::factory()->create([
            'type'              => 'one_time',
            'status'            => 'pending',
            'payment_intent_id' => 'pi_test_2',
        ]);

        $this->mock(StripeService::class, function ($mock) {
            $mock->shouldReceive('retrievePaymentIntent')
                ->once()
                ->andReturn($this->fakeStripePaymentIntent('pi_test_2', 'requires_payment_method'));

            // MUST NOT finalize if not succeeded
            $mock->shouldReceive('finalizeTransactionFromPaymentIntent')->never();
        });

        $res = $this->postJson(route('donations.complete'), [
            'mode'              => 'payment',
            'transaction_id'    => $tx->id,
            'payment_intent_id' => 'pi_test_2',
        ]);

        // Your controller currently returns 200 on non-succeeded
        $res->assertOk();

        $json = $res->json();

        $this->assertSame(false, $json['ok'] ?? null);
        $this->assertSame('requires_payment_method', $json['status'] ?? null);
    }

    #[Test]
    public function complete_succeeds_and_fills_all_columns_when_payment_intent_succeeded(): void
    {
        $tx = Transaction::factory()->create([
            'type'              => 'one_time',
            'payment_intent_id' => 'pi_test_3',
            'status'            => 'pending',
            'charge_id'         => null,
            'customer_id'       => null,
            'payment_method_id' => null,
            'receipt_url'       => null,
            'paid_at'           => null,
        ]);

        $pi = $this->fakeStripePaymentIntent('pi_test_3', 'succeeded');

        $this->mock(StripeService::class, function ($mock) use ($pi) {
            $mock->shouldReceive('retrievePaymentIntent')
                ->once()
                ->andReturn($pi);

            // match controller signature: finalizeTransactionFromPaymentIntent($tx, $pi)
            $mock->shouldReceive('finalizeTransactionFromPaymentIntent')
                ->once()
                ->withArgs(function ($transactionArg, $piArg) {
                    return $transactionArg instanceof Transaction
                        && ($piArg instanceof PaymentIntent);
                })
                ->andReturnUsing(function (Transaction $transaction) {
                    // simulate filled fields
                    $transaction->update([
                        'status'            => 'succeeded',
                        'customer_id'       => 'cus_test_1',
                        'payment_method_id' => 'pm_test_1',
                        'charge_id'         => 'ch_test_1',
                        'receipt_url'       => 'https://example.com/receipt',
                        'paid_at'           => now(),
                    ]);

                    return $transaction->fresh();
                });
        });

        $res = $this->postJson(route('donations.complete'), [
            'mode'              => 'payment',
            'transaction_id'    => $tx->id,
            'payment_intent_id' => 'pi_test_3',
        ]);

        $res->assertOk()->assertJson(['ok' => true]);

        $tx->refresh();

        $this->assertSame('pi_test_3', $tx->payment_intent_id);
        $this->assertSame('ch_test_1', $tx->charge_id);
        $this->assertSame('cus_test_1', $tx->customer_id);
        $this->assertSame('pm_test_1', $tx->payment_method_id);
        $this->assertNotNull($tx->paid_at);
        $this->assertSame('succeeded', $tx->status);
        $this->assertSame('one_time', $tx->type);
    }

    private function fakeStripePaymentIntent(string $id, string $status): PaymentIntent
    {
        // Stripe SDK objects are "StripeObject" with special setters,
        // so we use constructFrom to avoid "Cannot set id" exceptions.
        return PaymentIntent::constructFrom([
            'id'            => $id,
            'status'        => $status,
            'client_secret' => 'cs_test_' . $id,
        ]);
    }
}
