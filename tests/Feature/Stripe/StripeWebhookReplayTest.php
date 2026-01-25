<?php

declare(strict_types=1);

namespace Tests\Feature\Stripe;

use App\Http\Controllers\StripeWebhookController;
use App\Models\Pledge;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Stripe\StripeClient;
use Tests\Support\Stripe\FakeStripeClient;
use Tests\Support\Stripe\StripeEventFixture;
use Tests\TestCase;

class StripeWebhookReplayTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function subscription_create_webhook_burst_is_idempotent_and_updates_existing_pending_transaction(): void
    {
        User::unguard();
        Pledge::unguard();
        Transaction::unguard();

        $this->bindStripeFakeFromFixtures();

        $user = User::factory()->create(['id' => 1]);

        $pledge = Pledge::factory()->create([
            'id'                     => 6,
            'user_id'                => $user->id,
            'attempt_id'             => '22e6acd1-3c4b-481c-a258-9418e3ff6adc',
            'status'                 => 'active',
            'stripe_customer_id'     => 'cus_TpUFHUYkfaRJrq',
            'stripe_subscription_id' => 'sub_1SszSk1DPheI4KUEE0DIdyeV',
        ]);

        $pending = Transaction::create([
            'user_id'           => $user->id,
            'pledge_id'         => $pledge->id,
            'attempt_id'        => $pledge->attempt_id,
            'amount_cents'      => 113,
            'currency'          => 'usd',
            'type'              => 'subscription_initial',
            'payment_intent_id' => 'pi_3SszSl1DPheI4KUE1O4FTdi8',
            'status'            => 'pending',
            'source'            => 'donation_widget',
            'metadata'          => ['stage' => 'subscription_created'],
        ]);

        $controller = app()->make(StripeWebhookController::class);

        $events = StripeEventFixture::loadMany([
            'payment_intent.created.json',
            'payment_intent.succeeded.json',
            'charge.succeeded.json',
            'invoice.paid.json',
            'invoice_payment.paid.json',
        ]);

        foreach ($events as $event) {
            if (($event->type ?? null) === 'invoice.paid') {
                $this->patchInvoicePaidFixture($event);
            }
            if (($event->type ?? null) === 'invoice_payment.paid') {
                $this->patchInvoicePaymentPaidFixture($event);
            }
            if (($event->type ?? null) === 'payment_intent.succeeded') {
                $this->patchPaymentIntentSucceededFixtureForSubscription($event);
            }
            if (($event->type ?? null) === 'charge.succeeded') {
                $this->patchChargeSucceededFixtureForSubscription($event);
            }
        }

        foreach ($events as $event) {
            $controller->handleEvent($event);
        }

        foreach (array_reverse($events) as $event) {
            $controller->handleEvent($event);
        }

        $this->assertSame(
            1,
            Transaction::query()->where('payment_intent_id', $pending->payment_intent_id)->count(),
            'Should never create a duplicate transaction for the same payment_intent_id'
        );

        $tx = Transaction::query()->where('payment_intent_id', $pending->payment_intent_id)->firstOrFail();

        $this->assertPaidStageAndWriter($tx, 'invoice_paid');

        $this->assertContains(
            data_get($tx->metadata, 'event_type'),
            ['invoice.paid', 'invoice_payment.paid', 'invoice.payment_succeeded'],
            'event_type should reflect invoice writer origin'
        );

        $this->assertSame('subscription_create', data_get($tx->metadata, 'billing_reason'));

        $this->assertSame('ch_3SszSl1DPheI4KUE1i4XFtP7', $tx->charge_id);
        $this->assertSame('cus_TpUFHUYkfaRJrq', $tx->customer_id);
        $this->assertSame('pm_1SszSj1DPheI4KUETDOApOWo', $tx->payment_method_id);
        $this->assertSame('in_1SszSl1DPheI4KUE0Qzy4hom', $tx->stripe_invoice_id);

        $this->assertSame($pending->id, $tx->id, 'Should update the existing widget transaction row');
    }

    #[Test]
    public function charge_succeeded_arriving_before_invoice_paid_does_not_create_a_duplicate_tx_and_invoice_paid_claims_the_placeholder(): void
    {
        User::unguard();
        Pledge::unguard();
        Transaction::unguard();

        $this->bindStripeFakeFromFixtures();

        $user = User::factory()->create(['id' => 1]);

        $pledge = Pledge::factory()->create([
            'id'                     => 6,
            'user_id'                => $user->id,
            'attempt_id'             => '22e6acd1-3c4b-481c-a258-9418e3ff6adc',
            'status'                 => 'active',
            'stripe_customer_id'     => 'cus_TpUFHUYkfaRJrq',
            'stripe_subscription_id' => 'sub_1SszSk1DPheI4KUEE0DIdyeV',
        ]);

        $pending = Transaction::create([
            'user_id'           => $user->id,
            'pledge_id'         => $pledge->id,
            'attempt_id'        => $pledge->attempt_id,
            'amount_cents'      => 113,
            'currency'          => 'usd',
            'type'              => 'subscription_initial',
            'payment_intent_id' => null,
            'status'            => 'pending',
            'source'            => 'donation_widget',
            'metadata'          => ['stage' => 'subscription_created'],
        ]);

        $controller = app()->make(StripeWebhookController::class);

        $chargeSucceeded    = StripeEventFixture::load('charge.succeeded.json');
        $invoicePaid        = StripeEventFixture::load('invoice.paid.json');
        $invoicePaymentPaid = StripeEventFixture::load('invoice_payment.paid.json');

        $this->patchChargeSucceededFixtureForSubscription($chargeSucceeded);
        $this->patchInvoicePaidFixture($invoicePaid);
        $this->patchInvoicePaymentPaidFixture($invoicePaymentPaid);

        // 1) charge.succeeded first should NOT create any new tx
        $controller->handleEvent($chargeSucceeded);

        $this->assertSame(
            1,
            Transaction::query()->count(),
            'charge.succeeded must not create a new transaction'
        );

        // 2) invoice_payment.paid arrives (bridges via FakeStripeClient)
        $controller->handleEvent($invoicePaymentPaid);

        $tx = Transaction::query()->whereKey($pending->id)->firstOrFail();

        $this->assertSame('pi_3SszSl1DPheI4KUE1O4FTdi8', $tx->payment_intent_id);
        $this->assertSame('ch_3SszSl1DPheI4KUE1i4XFtP7', $tx->charge_id);
        $this->assertSame('cus_TpUFHUYkfaRJrq', $tx->customer_id);
        $this->assertSame('pm_1SszSj1DPheI4KUETDOApOWo', $tx->payment_method_id);
        $this->assertSame('in_1SszSl1DPheI4KUE0Qzy4hom', $tx->stripe_invoice_id);

        $this->assertPaidStageAndWriter($tx, 'invoice_paid');

        $this->assertContains(
            data_get($tx->metadata, 'event_type'),
            ['invoice.paid', 'invoice_payment.paid', 'invoice.payment_succeeded']
        );

        $this->assertSame('subscription_create', data_get($tx->metadata, 'billing_reason'));

        $this->assertSame(
            1,
            Transaction::query()->where('payment_intent_id', 'pi_3SszSl1DPheI4KUE1O4FTdi8')->count(),
            'Only one transaction may claim the payment_intent_id'
        );

        $this->assertSame($pending->id, $tx->id);

        // 3) Later invoice.paid arrives too; still idempotent
        $controller->handleEvent($invoicePaid);

        $this->assertSame(
            1,
            Transaction::query()->where('payment_intent_id', 'pi_3SszSl1DPheI4KUE1O4FTdi8')->count(),
            'invoice writer retry must not create duplicates'
        );
    }

    #[Test]
    public function subscription_create_burst_is_safe_under_any_event_ordering_and_retries(): void
    {
        User::unguard();
        Pledge::unguard();
        Transaction::unguard();

        $this->bindStripeFakeFromFixtures();

        $user = User::factory()->create(['id' => 1]);

        $pledge = Pledge::factory()->create([
            'id'                     => 6,
            'user_id'                => $user->id,
            'attempt_id'             => '22e6acd1-3c4b-481c-a258-9418e3ff6adc',
            'status'                 => 'active',
            'stripe_customer_id'     => 'cus_TpUFHUYkfaRJrq',
            'stripe_subscription_id' => 'sub_1SszSk1DPheI4KUEE0DIdyeV',
        ]);

        $pending = Transaction::create([
            'user_id'           => $user->id,
            'pledge_id'         => $pledge->id,
            'attempt_id'        => $pledge->attempt_id,
            'amount_cents'      => 113,
            'currency'          => 'usd',
            'type'              => 'subscription_initial',
            'payment_intent_id' => null,
            'status'            => 'pending',
            'source'            => 'donation_widget',
            'metadata'          => ['stage' => 'subscription_created'],
        ]);

        $controller = app()->make(StripeWebhookController::class);

        $events = StripeEventFixture::loadMany([
            'payment_intent.created.json',
            'payment_intent.succeeded.json',
            'charge.succeeded.json',
            'invoice.paid.json',
            'invoice_payment.paid.json',
        ]);

        foreach ($events as $event) {
            if (($event->type ?? null) === 'invoice.paid') {
                $this->patchInvoicePaidFixture($event);
            }
            if (($event->type ?? null) === 'invoice_payment.paid') {
                $this->patchInvoicePaymentPaidFixture($event);
            }
            if (($event->type ?? null) === 'payment_intent.succeeded') {
                $this->patchPaymentIntentSucceededFixtureForSubscription($event);
            }
            if (($event->type ?? null) === 'charge.succeeded') {
                $this->patchChargeSucceededFixtureForSubscription($event);
            }
        }

        foreach ($this->permute($events) as $i => $ordering) {
            Transaction::query()->whereKeyNot($pending->id)->delete();
            Transaction::query()->whereKey($pending->id)->update([
                'payment_intent_id' => null,
                'charge_id'         => null,
                'customer_id'       => null,
                'payment_method_id' => null,
                'stripe_invoice_id' => null,
                'status'            => 'pending',
                'paid_at'           => null,
                'metadata'          => ['stage' => 'subscription_created'],
            ]);

            foreach ($ordering as $event) {
                $controller->handleEvent($event);
            }

            foreach (array_reverse($ordering) as $event) {
                $controller->handleEvent($event);
            }

            $this->assertSame(
                1,
                Transaction::query()->where('payment_intent_id', 'pi_3SszSl1DPheI4KUE1O4FTdi8')->count(),
                'Permutation #'.$i.' created multiple rows claiming the same payment_intent_id'
            );

            $tx = Transaction::query()->whereKey($pending->id)->firstOrFail();

            $this->assertSame(
                'pi_3SszSl1DPheI4KUE1O4FTdi8',
                $tx->payment_intent_id,
                'Permutation #'.$i.' did not end with placeholder claiming the PI'
            );

            $this->assertPaidStageAndWriter($tx, 'invoice_paid');

            $this->assertSame(
                1,
                Transaction::query()->count(),
                'Permutation #'.$i.' created extra transaction rows'
            );
        }
    }

    #[Test]
    public function one_time_payment_intent_succeeded_marks_paid_and_sets_writer_metadata(): void
    {
        User::unguard();
        Transaction::unguard();

        $user = User::factory()->create(['id' => 1]);

        $tx = Transaction::create([
            'user_id'           => $user->id,
            'attempt_id'        => 'attempt_one_time_1',
            'type'              => 'one_time',
            'status'            => 'pending',
            'source'            => 'donation_widget',
            'amount_cents'      => 101,
            'currency'          => 'usd',
            'payment_intent_id' => 'pi_one_time_123',
            'metadata'          => ['stage' => 'details_submitted'],
        ]);

        /** @var StripeWebhookController $controller */
        $controller = app()->make(StripeWebhookController::class);

        $event = (object) [
            'id'   => 'evt_pi_succeeded_1',
            'type' => 'payment_intent.succeeded',
            'data' => (object) [
                'object' => (object) [
                    'id'            => 'pi_one_time_123',
                    'invoice'        => null,
                    'customer'       => 'cus_one_time_123',
                    'payment_method' => 'pm_one_time_123',
                    'latest_charge'  => 'ch_one_time_123',
                ],
            ],
        ];

        $controller->handleEvent($event);

        $tx->refresh();

        $this->assertSame('succeeded', $tx->status);
        $this->assertNotNull($tx->paid_at);
        $this->assertNull($tx->stripe_invoice_id);

        $this->assertSame('paid', data_get($tx->metadata, 'stage'));
        $this->assertSame('payment_intent_succeeded', data_get($tx->metadata, 'writer'));
        $this->assertSame('payment_intent.succeeded', data_get($tx->metadata, 'event'));
    }

    private function bindStripeFakeFromFixtures(): void
    {
        $invoicePaidEvent = StripeEventFixture::load('invoice.paid.json');
        $this->patchInvoicePaidFixture($invoicePaidEvent);

        $invoiceObj = $invoicePaidEvent->data->object;

        $piSucceeded = StripeEventFixture::load('payment_intent.succeeded.json');
        $piObj = $piSucceeded->data->object ?? null;

        $chargeSucceeded = StripeEventFixture::load('charge.succeeded.json');
        $chargeObj = $chargeSucceeded->data->object ?? null;

        $fake = new FakeStripeClient([
            'invoices' => [
                (string) ($invoiceObj->id ?? 'in_1SszSl1DPheI4KUE0Qzy4hom') => $invoiceObj,
            ],
            'payment_intents' => is_object($piObj) && isset($piObj->id)
                ? [(string) $piObj->id => $piObj]
                : [],
            'charges' => is_object($chargeObj) && isset($chargeObj->id)
                ? [(string) $chargeObj->id => $chargeObj]
                : [],
        ]);

        $this->app->instance(StripeClient::class, $fake);
    }

    private function patchInvoicePaidFixture(object $event): void
    {
        $invoice = $event->data->object ?? null;
        if (! is_object($invoice)) {
            return;
        }

        $invoice->id = $invoice->id ?? 'in_1SszSl1DPheI4KUE0Qzy4hom';

        $piId = 'pi_3SszSl1DPheI4KUE1O4FTdi8';
        $chId = 'ch_3SszSl1DPheI4KUE1i4XFtP7';
        $pmId = 'pm_1SszSj1DPheI4KUETDOApOWo';

        if (! isset($invoice->payment_intent)) {
            $invoice->payment_intent = (object) ['id' => $piId, 'payment_method' => $pmId];
        } else {
            if (is_string($invoice->payment_intent)) {
                $invoice->payment_intent = (object) ['id' => $invoice->payment_intent, 'payment_method' => $pmId];
            } elseif (is_object($invoice->payment_intent)) {
                $invoice->payment_intent->id = $invoice->payment_intent->id ?? $piId;
                $invoice->payment_intent->payment_method = $invoice->payment_intent->payment_method ?? $pmId;
            }
        }

        $invoice->charge = $invoice->charge ?? $chId;
        $invoice->customer = $invoice->customer ?? 'cus_TpUFHUYkfaRJrq';
        $invoice->subscription = $invoice->subscription ?? 'sub_1SszSk1DPheI4KUEE0DIdyeV';

        $invoice->currency = $invoice->currency ?? 'usd';
        $invoice->amount_paid = $invoice->amount_paid ?? 113;

        $invoice->hosted_invoice_url = $invoice->hosted_invoice_url ?? 'https://example.test/invoice/'.$invoice->id;
        $invoice->customer_email     = $invoice->customer_email ?? 'donor@example.test';
        $invoice->customer_name      = $invoice->customer_name ?? 'Donor Person';

        if (! isset($invoice->status_transitions) || ! is_object($invoice->status_transitions)) {
            $invoice->status_transitions = (object) [];
        }
        if (! isset($invoice->status_transitions->paid_at)) {
            $invoice->status_transitions->paid_at = now()->timestamp;
        }

        $invoice->billing_reason = $invoice->billing_reason ?? 'subscription_create';
    }

    private function patchInvoicePaymentPaidFixture(object $event): void
    {
        $obj = $event->data->object ?? null;
        if (! is_object($obj)) {
            return;
        }

        $obj->invoice = $obj->invoice ?? 'in_1SszSl1DPheI4KUE0Qzy4hom';
    }

    private function patchPaymentIntentSucceededFixtureForSubscription(object $event): void
    {
        $pi = $event->data->object ?? null;
        if (! is_object($pi)) {
            return;
        }

        $pi->id = $pi->id ?? 'pi_3SszSl1DPheI4KUE1O4FTdi8';

        // critical: ties PI to subscription invoice so PI handler exits early
        $pi->invoice = $pi->invoice ?? 'in_1SszSl1DPheI4KUE0Qzy4hom';
    }

    private function patchChargeSucceededFixtureForSubscription(object $event): void
    {
        $ch = $event->data->object ?? null;
        if (! is_object($ch)) {
            return;
        }

        $ch->id = $ch->id ?? 'ch_3SszSl1DPheI4KUE1i4XFtP7';
        $ch->payment_intent = $ch->payment_intent ?? 'pi_3SszSl1DPheI4KUE1O4FTdi8';

        // critical: ties charge to invoice so charge handler treats it as subscription world
        $ch->invoice = $ch->invoice ?? 'in_1SszSl1DPheI4KUE0Qzy4hom';
    }

    /**
     * @param  array<int, object>  $items
     * @return \Generator<int, array<int, object>>
     */
    private function permute(array $items): \Generator
    {
        $count = count($items);

        if ($count <= 1) {
            yield $items;
            return;
        }

        foreach ($items as $index => $item) {
            $rest = $items;
            unset($rest[$index]);
            $rest = array_values($rest);

            foreach ($this->permute($rest) as $perm) {
                array_unshift($perm, $item);
                yield $perm;
            }
        }
    }

    private function assertPaidStageAndWriter(Transaction $tx, string $writer): void
    {
        $this->assertSame('succeeded', $tx->status);
        $this->assertNotNull($tx->paid_at);

        $this->assertSame('paid', data_get($tx->metadata, 'stage'));
        $this->assertSame($writer, data_get($tx->metadata, 'writer'));
    }
}
