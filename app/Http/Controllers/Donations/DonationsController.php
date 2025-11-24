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

    public function show()
    {
        return view('donations.give');
    }

    public function start(Request $request)
    {
        $data = $request->validate([
            'amount'    => ['required', 'numeric', 'min:1'],
            'frequency' => ['required', 'in:one_time,monthly'],
        ]);

        $amountCents = (int) round($data['amount'] * 100);
        $frequency   = $data['frequency'];
        $user        = Auth::user();

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
     * Front-end MUST call this after Stripe confirms.
     */
    public function complete(Request $request)
    {
        $data = $request->validate([
            'mode'              => ['required', 'in:payment,subscription'],
            'transaction_id'    => ['nullable', 'integer', 'required_if:mode,payment'],
            'pledge_id'         => ['nullable', 'integer', 'required_if:mode,subscription'],

            'payment_intent_id' => ['nullable', 'string'],
            'charge_id'         => ['nullable', 'string'],
            'payment_method_id' => ['nullable', 'string', 'required_if:mode,subscription'],
            'receipt_url'       => ['nullable', 'url'],

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

        // ---------------------------------------------------------
        // Update authenticated user + primary address
        // ---------------------------------------------------------
        if ($user = Auth::user()) {
            $user->fill([
                'first_name' => $data['donor_first_name'] ?? $user->first_name,
                'last_name'  => $data['donor_last_name']  ?? $user->last_name,
                'email'      => $data['donor_email']      ?? $user->email,
            ])->save();

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
                    'first_name'  => $data['donor_first_name'] ?? $primary->first_name ?? $user->first_name,
                    'last_name'   => $data['donor_last_name']  ?? $primary->last_name  ?? $user->last_name,
                    'phone'       => $data['donor_phone']      ?? $primary->phone,
                    'line1'       => $data['address_line1']    ?? $primary->line1,
                    'line2'       => $data['address_line2']    ?? $primary->line2,
                    'city'        => $data['address_city']     ?? $primary->city,
                    'state'       => $data['address_state']    ?? $primary->state,
                    'postal_code' => $data['address_postal']   ?? $primary->postal_code,
                    'country'     => $data['address_country']  ?? $primary->country ?? 'US',
                    'is_primary'  => true,
                ])->save();
            }
        }

        $fullName = trim(
            ($data['donor_first_name'] ?? '') . ' ' . ($data['donor_last_name'] ?? '')
        );

        // ---------------------------------------------------------
        // One-time payment completion
        // ---------------------------------------------------------
        if ($data['mode'] === 'payment') {
            $transaction = Transaction::findOrFail($data['transaction_id']);

            $transaction->fill([
                'payment_intent_id' => $data['payment_intent_id'] ?? $transaction->payment_intent_id,
                'charge_id'         => $data['charge_id']         ?? $transaction->charge_id,
                'payment_method_id' => $data['payment_method_id'] ?? $transaction->payment_method_id,
                'receipt_url'       => $data['receipt_url']       ?? $transaction->receipt_url,

                'payer_email'       => $data['donor_email']       ?? $transaction->payer_email,
                'payer_name'        => $fullName ?: $transaction->payer_name,

                'status'            => 'succeeded',
                'paid_at'           => now(),
            ])->save();

            // single-use key for the thank-you page
            $request->session()->put('transaction_thankyou_id', $transaction->id);

            if ($request->wantsJson()) {
                return response()->json([
                    'redirect' => route('donations.thankyou'),
                ]);
            }

            return redirect()
                ->route('donations.thankyou')
                ->with('success', 'Thank you for your donation!');
        }

        // ---------------------------------------------------------
        // Subscription completion
        // ---------------------------------------------------------
        $pledge = Pledge::findOrFail($data['pledge_id']);

        if (! empty($data['donor_email'])) {
            $pledge->donor_email = $data['donor_email'];
        }
        if ($fullName !== '') {
            $pledge->donor_name = $fullName;
        }
        $pledge->save();

        // IMPORTANT: actually create the Stripe subscription here
        // StripeService should set stripe_subscription_id + stripe_customer_id on pledge.
        $this->stripe->createSubscriptionForPledge(
            $pledge,
            $data['payment_method_id']
        );

        $request->session()->put('pledge_thankyou_id', $pledge->id);

        if ($request->wantsJson()) {
            return response()->json([
                'redirect' => route('donations.thankyou-subscription'),
            ]);
        }

        return redirect()
            ->route('donations.thankyou-subscription')
            ->with('success', 'Thank you for your monthly pledge!');
    }

    /**
     * One-time thank-you page: requires a session key and works once.
     */
    public function thankYou(Request $request)
    {
        $transactionId = $request->session()->pull('transaction_thankyou_id');

        if (! $transactionId) {
            abort(404);
        }

        $transaction = Transaction::findOrFail($transactionId);

        return view('donations.thankyou', compact('transaction'));
    }

    /**
     * Subscription thank-you page: also requires a session key and works once.
     */
    public function thankYouSubscription(Request $request)
    {
        $pledgeId = $request->session()->pull('pledge_thankyou_id');

        if (! $pledgeId) {
            abort(404);
        }

        $pledge = Pledge::findOrFail($pledgeId);

        $subscriptionTransaction = Transaction::where('pledge_id', $pledge->id)
            ->whereIn('type', ['subscription_initial', 'subscription_recurring'])
            ->latest('paid_at')
            ->first();

        return view('donations.thankyou-subscription', [
            'pledge'                  => $pledge,
            'subscriptionTransaction' => $subscriptionTransaction,
        ]);
    }
}
