<?php

namespace Tests\Feature\Stripe;

use App\Http\Controllers\StripeWebhookController;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StripeWebhookOneTimeDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_time_charge_succeeded_enriches_existing_pending_placeholder_and_preserves_user_id_and_card_metadata(): void
    {
        $user = User::factory()->create();

        $placeholder = Transaction::factory()->create([
            'user_id'           => $user->id,
            'pledge_id'         => null,
            'type'              => 'one_time',
            'status'            => 'pending',
            'payment_intent_id' => null,
            'charge_id'         => null,
            'customer_id'       => 'cus_abc',
            'payment_method_id' => null,
            'amount_cents'      => 701,
            'currency'          => 'usd',
            'payer_email'       => 'donor@example.test',
            'payer_name'        => 'Test Donor',
            'receipt_url'       => null,
            'source'            => 'donation_widget',
            'metadata'          => ['frequency' => 'one_time'],
            'created_at'        => now(),
        ]);

        $event = (object) [
            'type' => 'charge.succeeded',
            'data' => (object) [
                'object' => (object) [
                    'id'            => 'ch_123',
                    'invoice'        => null, // one-time path
                    'payment_intent' => 'pi_123',
                    'customer'       => 'cus_abc',
                    'payment_method' => 'pm_123',
                    'amount'         => 701,
                    'currency'       => 'usd',
                    'receipt_url'    => 'https://example.test/receipt/ch_123',
                    'billing_details' => (object) [
                        'email' => 'donor@example.test',
                        'name'  => 'Test Donor',
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

        $this->assertDatabaseCount('transactions', 1);

        $placeholder->refresh();

        // ✅ preserve user_id
        $this->assertSame($user->id, $placeholder->user_id);

        // ✅ core fields
        $this->assertSame('succeeded', $placeholder->status);
        $this->assertSame('pi_123', $placeholder->payment_intent_id);
        $this->assertSame('ch_123', $placeholder->charge_id);
        $this->assertSame('cus_abc', $placeholder->customer_id);
        $this->assertSame('pm_123', $placeholder->payment_method_id);
        $this->assertSame('https://example.test/receipt/ch_123', $placeholder->receipt_url);
        $this->assertSame('stripe_webhook', $placeholder->source);
        $this->assertNotNull($placeholder->paid_at);

        // ✅ thank-you page metadata
        $this->assertIsArray($placeholder->metadata);
        $this->assertSame('visa', data_get($placeholder->metadata, 'card_brand'));
        $this->assertSame('4242', data_get($placeholder->metadata, 'card_last4'));
        $this->assertSame('US', data_get($placeholder->metadata, 'card_country'));
        $this->assertSame('credit', data_get($placeholder->metadata, 'card_funding'));
    }

    public function test_one_time_charge_succeeded_creates_transaction_when_no_placeholder_exists(): void
    {
        $event = (object) [
            'type' => 'charge.succeeded',
            'data' => (object) [
                'object' => (object) [
                    'id'            => 'ch_new',
                    'invoice'        => null,
                    'payment_intent' => 'pi_new',
                    'customer'       => 'cus_new',
                    'payment_method' => 'pm_new',
                    'amount'         => 100,
                    'currency'       => 'usd',
                    'receipt_url'    => 'https://example.test/receipt/ch_new',
                    'billing_details' => (object) [
                        'email' => 'donor@example.test',
                        'name'  => 'Test Donor',
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

        $tx = Transaction::firstOrFail();

        $this->assertSame('one_time', $tx->type);
        $this->assertSame('succeeded', $tx->status);
        $this->assertSame('pi_new', $tx->payment_intent_id);
        $this->assertSame('ch_new', $tx->charge_id);
        $this->assertSame('cus_new', $tx->customer_id);
        $this->assertSame('pm_new', $tx->payment_method_id);
        $this->assertSame('https://example.test/receipt/ch_new', $tx->receipt_url);
        $this->assertSame('stripe_webhook', $tx->source);

        $this->assertSame('visa', data_get($tx->metadata, 'card_brand'));
        $this->assertSame('4242', data_get($tx->metadata, 'card_last4'));
    }
}
