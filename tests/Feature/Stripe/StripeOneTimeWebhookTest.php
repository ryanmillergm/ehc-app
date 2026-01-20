<?php

namespace Tests\Feature\Stripe;

use App\Http\Controllers\StripeWebhookController;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StripeOneTimeWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_time_charge_succeeded_enriches_existing_placeholder_with_charge_receipt_and_card_metadata(): void
    {
        $user = User::factory()->create();

        $piId = 'pi_test_123';
        $chId = 'ch_test_123';
        $cus  = 'cus_test_123';
        $pm   = 'pm_test_123';

        Transaction::forceCreate([
            'user_id'           => $user->id,
            'pledge_id'         => null,
            'payment_intent_id' => $piId,
            'subscription_id'   => null,
            'charge_id'         => null,
            'customer_id'       => $cus,
            'payment_method_id' => null,
            'amount_cents'      => 701,
            'currency'          => 'usd',
            'type'              => 'one_time',
            'status'            => 'pending',
            'payer_email'       => null,
            'payer_name'        => null,
            'receipt_url'       => null,
            'source'            => 'donation_widget',
            'metadata'          => ['frequency' => 'one_time'],
            'paid_at'           => null,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        $event = (object) [
            'type' => 'charge.succeeded',
            'data' => (object) [
                'object' => (object) [
                    'id'            => $chId,
                    'payment_intent' => $piId,
                    'customer'      => $cus,
                    'payment_method'=> $pm,
                    'amount'        => 701,
                    'currency'      => 'usd',
                    'receipt_url'   => 'https://pay.stripe.com/receipts/payment/xyz',
                    'billing_details' => (object) [
                        'email' => 'ryanmillergm@gmail.com',
                        'name'  => 'Ryan Miller',
                    ],
                    'payment_method_details' => (object) [
                        'card' => (object) [
                            'brand'   => 'visa',
                            'last4'   => '4242',
                            'country' => 'US',
                            'funding' => 'credit',
                        ],
                    ],
                ],
            ],
        ];

        (new StripeWebhookController())->handleEvent($event);

        $tx = Transaction::firstOrFail();
        $tx->refresh();

        $this->assertSame('succeeded', $tx->status);
        $this->assertSame($user->id, $tx->user_id);
        $this->assertSame($piId, $tx->payment_intent_id);
        $this->assertSame($chId, $tx->charge_id);
        $this->assertSame($pm, $tx->payment_method_id);
        $this->assertSame('https://pay.stripe.com/receipts/payment/xyz', $tx->receipt_url);
        $this->assertSame('ryanmillergm@gmail.com', $tx->payer_email);
        $this->assertSame('Ryan Miller', $tx->payer_name);

        $this->assertSame('visa', data_get($tx->metadata, 'card_brand'));
        $this->assertSame('4242', data_get($tx->metadata, 'card_last4'));
        $this->assertSame('US', data_get($tx->metadata, 'card_country'));
        $this->assertSame('credit', data_get($tx->metadata, 'card_funding'));
    }

    public function test_one_time_charge_succeeded_creates_transaction_if_no_placeholder_exists(): void
    {
        $event = (object) [
            'type' => 'charge.succeeded',
            'data' => (object) [
                'object' => (object) [
                    'id'            => 'ch_new',
                    'payment_intent' => 'pi_new',
                    'customer'      => 'cus_new',
                    'payment_method'=> 'pm_new',
                    'amount'        => 500,
                    'currency'      => 'usd',
                    'receipt_url'   => 'https://pay.stripe.com/receipts/payment/new',
                    'billing_details' => (object) [
                        'email' => 'donor@example.test',
                        'name'  => 'Donor Name',
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

        (new StripeWebhookController())->handleEvent($event);

        $this->assertDatabaseCount('transactions', 1);

        $this->assertDatabaseHas('transactions', [
            'payment_intent_id' => 'pi_new',
            'charge_id'         => 'ch_new',
            'customer_id'       => 'cus_new',
            'payment_method_id' => 'pm_new',
            'type'              => 'one_time',
            'status'            => 'succeeded',
            'receipt_url'       => 'https://pay.stripe.com/receipts/payment/new',
        ]);
    }
}
