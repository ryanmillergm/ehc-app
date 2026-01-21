<?php

namespace Tests\Feature\Donations;

use App\Models\Pledge;
use App\Models\Transaction;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Stripe\PaymentIntent;
use Stripe\SetupIntent;
use Tests\TestCase;

class StartDonationTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_start_one_time_donation(): void
    {
        $this->mock(StripeService::class, function ($mock) {
            $mock->shouldReceive('createOneTimePaymentIntent')
                ->andReturn(
                    PaymentIntent::constructFrom([
                        'id'            => 'pi_test_123',
                        'client_secret' => 'pi_test_secret',
                    ])
                );
        });

        $response = $this->postJson(route('donations.start'), [
            'amount'    => 25,
            'frequency' => 'one_time',
        ]);

        $response->assertOk()
            ->assertJsonFragment([
                'mode' => 'payment',
            ])
            ->assertJsonStructure(['clientSecret', 'transactionId']);

        $this->assertDatabaseHas('transactions', [
            'amount_cents' => 2500,
            'type'         => 'one_time',
            'status'       => 'pending',
        ]);
    }

    public function test_can_start_monthly_donation(): void
    {
        $this->mock(StripeService::class, function ($mock) {
            $mock->shouldReceive('createSetupIntentForPledge')
                ->andReturn(
                    SetupIntent::constructFrom([
                        'id'            => 'seti_test_123',
                        'client_secret' => 'seti_test_secret',
                    ])
                );
        });

        $response = $this->postJson(route('donations.start'), [
            'amount'    => 15,
            'frequency' => 'monthly',
        ]);

        $response->assertOk()
            ->assertJsonFragment([
                'mode' => 'subscription',
            ])
            ->assertJsonStructure(['clientSecret', 'pledgeId']);

        $this->assertDatabaseHas('pledges', [
            'amount_cents' => 1500,
            'interval'     => 'month',
            'status'       => 'incomplete',
        ]);
    }

    public function test_one_time_same_attempt_different_amount_creates_new_attempt_and_new_transaction(): void
    {
        // Mock Stripe so we don't hit the network.
        $this->mock(StripeService::class, function ($mock) {
            $pi1 = \Stripe\PaymentIntent::constructFrom([
                'id'            => 'pi_test_1',
                'client_secret' => 'cs_test_1',
                'status'        => 'requires_payment_method',
            ]);

            $pi2 = \Stripe\PaymentIntent::constructFrom([
                'id'            => 'pi_test_2',
                'client_secret' => 'cs_test_2',
                'status'        => 'requires_payment_method',
            ]);

            $mock->shouldReceive('createOneTimePaymentIntent')
                ->twice()
                ->andReturn($pi1, $pi2);
        });

        // 1) Start WITHOUT attempt_id (server creates it)
        $r1 = $this->postJson(route('donations.start'), [
            'amount'     => 25,
            'frequency'  => 'one_time',
        ])->assertOk()->json();

        $this->assertSame('payment', $r1['mode']);
        $this->assertNotEmpty($r1['attemptId']);

        $attempt = $r1['attemptId'];

        $tx1 = Transaction::findOrFail($r1['transactionId']);
        $this->assertSame(2500, $tx1->amount_cents);
        $this->assertSame($attempt, $tx1->attempt_id);

        // 2) Same attempt comes back, but with a DIFFERENT amount => new attempt + new tx
        $r2 = $this->postJson(route('donations.start'), [
            'attempt_id' => $attempt,
            'amount'     => 50,
            'frequency'  => 'one_time',
        ])->assertOk()->json();

        $this->assertNotSame($attempt, $r2['attemptId']);

        $tx2 = Transaction::findOrFail($r2['transactionId']);
        $this->assertSame(5000, $tx2->amount_cents);
        $this->assertSame($r2['attemptId'], $tx2->attempt_id);

        $this->assertNotSame($tx1->id, $tx2->id);
    }
}
