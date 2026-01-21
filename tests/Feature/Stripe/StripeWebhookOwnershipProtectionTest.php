<?php

namespace Tests\Feature\Stripe;

use App\Http\Controllers\StripeWebhookController;
use App\Models\Pledge;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AssertsStripeWebhookState;
use Tests\TestCase;

class StripeWebhookOwnershipProtectionTest extends TestCase
{
    use RefreshDatabase;
    use AssertsStripeWebhookState;

    public function test_invoice_paid_does_not_steal_payment_intent_from_another_transaction(): void
    {
        $pledgeA = Pledge::forceCreate([
            'user_id'                => null,
            'amount_cents'           => 1500,
            'currency'               => 'usd',
            'interval'               => 'month',
            'status'                 => 'incomplete',
            'donor_email'            => 'donor@example.test',
            'donor_name'             => 'Test Donor',
            'stripe_subscription_id' => 'sub_A',
            'stripe_customer_id'     => 'cus_A',
        ]);

        // Placeholder for pledge A (the one we'd "normally" update)
        $placeholderA = Transaction::factory()->create([
            'pledge_id'         => $pledgeA->id,
            'subscription_id'   => 'sub_A',
            'type'              => 'subscription_recurring',
            'status'            => 'pending',
            'payment_intent_id' => null,
            'charge_id'         => null,
            'customer_id'       => 'cus_A',
            'metadata'          => [],
        ]);

        // Another tx already owns the PI (different pledge, or null pledge)
        $owner = Transaction::factory()->create([
            'pledge_id'         => null,
            'type'              => 'subscription_recurring',
            'status'            => 'succeeded',
            'payment_intent_id' => 'pi_owned',
            'charge_id'         => 'ch_owned',
            'customer_id'       => 'cus_other',
            'metadata'          => [],
        ]);

        $periodStart = now()->timestamp;
        $periodEnd   = now()->addMonth()->timestamp;

        $event = (object) [
            'type' => 'invoice.paid',
            'data' => (object) [
                'object' => (object) [
                    'id'                 => 'in_owned',
                    'billing_reason'     => 'subscription_cycle',
                    'subscription'       => 'sub_A',
                    'customer'           => 'cus_A',
                    'customer_email'     => 'donor@example.test',
                    'status'             => 'paid',
                    'amount_paid'        => 1500,
                    'currency'           => 'usd',
                    'hosted_invoice_url' => 'https://example.test/invoices/in_owned',
                    'payment_intent'     => (object) [
                        'id'             => 'pi_owned',
                        'payment_method' => 'pm_owned',
                    ],
                    'charge' => 'ch_new_should_not_override_owner',
                    'lines' => (object) [
                        'data' => [
                            (object) [
                                'subscription' => 'sub_A',
                                'period' => (object) [
                                    'start' => $periodStart,
                                    'end'   => $periodEnd,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        (new StripeWebhookController())->handleEvent($event);

        $this->assertSame(2, Transaction::count(), 'should not create new rows; should update existing owner');

        $placeholderA->refresh();
        $owner->refresh();

        // Placeholder must NOT have PI assigned
        $this->assertNull($placeholderA->payment_intent_id);

        // Owner gets invoice enrichment (your code chooses PI owner when it exists)
        $this->assertSame('in_owned', $owner->stripe_invoice_id);
        $this->assertSame('pi_owned', $owner->payment_intent_id);
        $this->assertSame('https://example.test/invoices/in_owned', $owner->receipt_url);
    }
}
