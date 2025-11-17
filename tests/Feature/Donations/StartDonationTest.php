<?php

namespace Tests\Feature\Donations;

use App\Models\Pledge;
use App\Models\Transaction;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
