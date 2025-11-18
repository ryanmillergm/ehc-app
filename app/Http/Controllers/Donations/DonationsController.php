<?php

namespace App\Http\Controllers\Donations;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Pledge;
use App\Models\Transaction;
use App\Services\StripeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DonationsController extends Controller
{
    public function __construct(
        protected StripeService $stripe
    ) {}

    /**
     * Example full-page donation route (could just as well be embedded).
     */
    public function show()
    {
        return view('donations.give');
    }

    /**
     * Step 1:
     *  - validate amount + frequency
     *  - create Transaction (one-time) OR Pledge (monthly)
     *  - create PaymentIntent or SetupIntent
     *  - return clientSecret + IDs as JSON for Stripe.js on the page
     */
    public function start(Request $request)
    {
        $data = $request->validate([
            'amount'    => ['required', 'numeric', 'min:1'],
            'frequency' => ['required', 'in:one_time,monthly'],
        ]);

        $amountCents = (int) round($data['amount'] * 100);
        $frequency   = $data['frequency'];
        $user        = Auth::user();

        // Basic donor info for creating Stripe customer
        $donor = [
            'email' => $user?->email,
            'name'  => $user ? trim("{$user->name}") : null,
        ];

        if ($frequency === 'one_time') {
            $transaction = Transaction::create([
                'user_id'      => $user?->id,
                'amount_cents' => $amountCents,
                'currency'     => 'usd',
                'type'         => 'one_time',
                'status'       => 'pending',
                'source'       => 'donation_widget',
                'metadata'     => [
                    'frequency' => 'one_time',
                ],
            ]);

            $pi = $this->stripe->createOneTimePaymentIntent($transaction, $donor);

            return response()->json([
                'mode'          => 'payment',
                'transactionId' => $transaction->id,
                'clientSecret'  => $pi->client_secret,
            ]);
        }

        // Monthly recurring: create a pledge + SetupIntent
        $pledge = Pledge::create([
            'user_id'      => $user?->id,
            'amount_cents' => $amountCents,
            'currency'     => 'usd',
            'interval'     => 'month',
            'status'       => 'incomplete',
            'donor_email'  => $donor['email'] ?? null,
            'donor_name'   => $donor['name'] ?? null,
            'metadata'     => [
                'frequency' => 'monthly',
            ],
        ]);

        $setupIntent = $this->stripe->createSetupIntentForPledge($pledge, $donor);

        return response()->json([
            'mode'         => 'subscription',
            'pledgeId'     => $pledge->id,
            'clientSecret' => $setupIntent->client_secret,
        ]);
    }

    /**
     * Called from the front-end after Stripe confirms the payment
     * (for one-time payments) or setup (monthly).
     *
     * Webhooks also run for robustness, but this gives immediate redirect behavior.
     */
    public function complete(Request $request)
    {
        $data = $request->validate([
            'mode'           => ['required', 'in:payment,subscription'],
            'transaction_id' => ['nullable', 'integer', 'required_if:mode,payment'],
            'pledge_id'      => ['nullable', 'integer', 'required_if:mode,subscription'],

            'payment_intent_id'   => ['nullable', 'string'], // one-time only
            'subscription_id'     => ['nullable', 'string'], // not used for now
            'charge_id'           => ['nullable', 'string'],
            'payment_method_id'   => ['nullable', 'string', 'required_if:mode,subscription'],
            'receipt_url'         => ['nullable', 'url'],

            'donor_first_name' => ['nullable', 'string', 'max:100'],
            'donor_last_name'  => ['nullable', 'string', 'max:100'],
            'donor_email'      => ['nullable', 'email'],
            'donor_phone'      => ['nullable', 'string', 'max:50'],
            'address_line1'    => ['nullable', 'string', 'max:255'],
            'address_line2'    => ['nullable', 'string', 'max:255'],
            'address_city'     => ['nullable', 'string', 'max:100'],
            'address_state'    => ['nullable', 'string', 'max:100'],
            'address_postal'   => ['nullable', 'string', 'max:20'],
            'address_country'  => ['nullable', 'string', 'max:2'],
        ]);

        // 1) Update user basic info (NO card details)
        if ($user = Auth::user()) {
            $user->fill([
                'first_name' => $data['donor_first_name'] ?? $user->first_name,
                'last_name'  => $data['donor_last_name'] ?? $user->last_name,
                'email'      => $data['donor_email'] ?? $user->email,
            ])->save();

            // 2) Upsert their primary address if any address fields given
            $hasAddressInput = ! empty($data['address_line1'])
                || ! empty($data['address_city'])
                || ! empty($data['address_postal']);

            if ($hasAddressInput) {
                $primary = Address::firstOrNew([
                    'user_id'    => $user->id,
                    'is_primary' => true,
                ]);

                if (! $primary->exists) {
                    $primary->label = 'Primary';
                }

                $primary->fill([
                    'first_name'   => $data['donor_first_name'] ?? $primary->first_name ?? $user->first_name,
                    'last_name'    => $data['donor_last_name']  ?? $primary->last_name  ?? $user->last_name,
                    'phone'        => $data['donor_phone']      ?? $primary->phone,
                    'line1'        => $data['address_line1']    ?? $primary->line1,
                    'line2'        => $data['address_line2']    ?? $primary->line2,
                    'city'         => $data['address_city']     ?? $primary->city,
                    'state'        => $data['address_state']    ?? $primary->state,
                    'postal_code'  => $data['address_postal']   ?? $primary->postal_code,
                    'country'      => $data['address_country']  ?? $primary->country ?? 'US',
                    'is_primary'   => true,
                ])->save();
            }
        }

        // ----- One-time payment completion -----
        if ($data['mode'] === 'payment') {
            /** @var \App\Models\Transaction $transaction */
            $transaction = Transaction::findOrFail($data['transaction_id']);

            // Build a full name from the donor data
            $fullName = trim(
                ($data['donor_first_name'] ?? '') . ' ' . ($data['donor_last_name'] ?? '')
            );

            $transaction->fill([
                'payment_intent_id' => $data['payment_intent_id'] ?? $transaction->payment_intent_id,
                'charge_id'         => $data['charge_id']         ?? $transaction->charge_id,
                'payment_method_id' => $data['payment_method_id'] ?? $transaction->payment_method_id,
                'receipt_url'       => $data['receipt_url']       ?? $transaction->receipt_url,

                // store who actually paid
                'payer_email'       => $data['donor_email']       ?? $transaction->payer_email,
                'payer_name'        => $fullName                  ?: $transaction->payer_name,

                'status'            => 'succeeded',
                'paid_at'           => now(),
            ])->save();

            return redirect()
                ->route('donations.thankyou', $transaction)
                ->with('success', 'Thank you for your donation!');
        }

        // ----- Subscription completion -----
        /** @var \App\Models\Pledge $pledge */
        $pledge = Pledge::findOrFail($data['pledge_id']);

        $fullName = trim(
            ($data['donor_first_name'] ?? '') . ' ' . ($data['donor_last_name'] ?? '')
        );

        // Keep donor info on the pledge up to date
        if (! empty($data['donor_email'])) {
            $pledge->donor_email = $data['donor_email'];
        }
        if ($fullName !== '') {
            $pledge->donor_name = $fullName;
        }
        $pledge->save();

        // Create the actual Stripe subscription using the saved payment method.
        // This will also update $pledge with subscription + latest invoice info.
        $this->stripe->createSubscriptionForPledge(
            $pledge,
            $data['payment_method_id']
        );

        // All actual recurring charges (including the first) will be reflected
        // via invoice.paid → handleInvoicePaid() + Transaction rows.
        return redirect()
            ->route('donations.thankyou-subscription', $pledge)
            ->with('success', 'Thank you for your monthly pledge!');
    }

    public function thankYou(Transaction $transaction)
    {
        return view('donations.thankyou', compact('transaction'));
    }

    public function thankYouSubscription(Pledge $pledge)
    {
        $subscriptionTransaction = Transaction::where('pledge_id', $pledge->id)
            ->latest('paid_at')
            ->first();

        return view('donations.thankyou-subscription', [
            'pledge'                  => $pledge,
            'subscriptionTransaction' => $subscriptionTransaction,
        ]);
    }
}
