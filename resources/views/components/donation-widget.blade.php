@php
    $user = auth()->user();
    $primaryAddress = $user?->primaryAddress; // or $user?->primaryAddressOrFirst
@endphp

@props([
    'startUrl'    => route('donations.start'),
    'completeUrl' => route('donations.complete'),
    'stripeKey'   => config('services.stripe.key'),
])

<div
    x-data="donationWidget(@js([
        'startUrl'    => $startUrl,
        'completeUrl' => $completeUrl,
        'stripeKey'   => $stripeKey,
        'prefill'     => [
            'first_name' => $user->first_name ?? '',
            'last_name'  => $user->last_name ?? '',
            'email'      => $user->email ?? '',
            'phone'      => $primaryAddress->phone ?? '',
            'address_line1'   => $primaryAddress->line1 ?? '',
            'address_line2'   => $primaryAddress->line2 ?? '',
            'address_city'    => $primaryAddress->city ?? '',
            'address_state'   => $primaryAddress->state ?? '',
            'address_postal'  => $primaryAddress->postal_code ?? '',
            'address_country' => $primaryAddress->country ?? '',
        ],
    ]))"
    x-init="init()"
    class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-6"
>
    {{-- Step 1: amount + frequency --}}
    <div x-show="step === 1" x-cloak class="space-y-6">
        <div class="space-y-6">
            <div class="flex gap-2 text-sm font-medium bg-slate-100 rounded-full p-1">
                <button
                    type="button"
                    @click="frequency = 'one_time'"
                    :class="frequency === 'one_time'
                        ? 'flex-1 rounded-full bg-white shadow px-3 py-2 text-slate-900'
                        : 'flex-1 rounded-full px-3 py-2 text-slate-500'"
                >
                    One-time
                </button>
                <button
                    type="button"
                    @click="frequency = 'monthly'"
                    :class="frequency === 'monthly'
                        ? 'flex-1 rounded-full bg-white shadow px-3 py-2 text-slate-900'
                        : 'flex-1 rounded-full px-3 py-2 text-slate-500'"
                >
                    Monthly
                </button>
            </div>

            <div class="grid grid-cols-3 gap-3">
                <template x-for="preset in presets" :key="preset">
                    <button
                        type="button"
                        @click="selectPreset(preset)"
                        :class="amount === preset
                            ? 'border-indigo-500 bg-indigo-50 text-indigo-700'
                            : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300'"
                        class="rounded-xl border px-3 py-2 text-sm font-medium"
                        x-text="`$${preset}`"
                    ></button>
                </template>
            </div>

            <div class="space-y-1">
                <label class="block text-xs font-medium text-slate-600">
                    Custom amount
                </label>
                <div class="relative rounded-xl border border-slate-200 px-3 py-2 flex items-center gap-2">
                    <span class="text-slate-400 text-sm">$</span>
                    <input
                        x-model.number="customAmount"
                        type="number"
                        min="1"
                        step="1"
                        class="w-full border-none focus:ring-0 text-sm text-slate-900"
                        placeholder="Enter amount"
                    >
                </div>
                <p class="text-xs text-slate-500">
                    You are giving <span class="font-semibold" x-text="summaryLabel()"></span>.
                </p>
            </div>

            <div>
                <button
                    type="button"
                    @click="startDonation()"
                    :disabled="loading || totalAmount() < 1"
                    class="inline-flex w-full items-center justify-center rounded-full bg-indigo-600
                           px-4 py-2.5 text-sm font-semibold text-white shadow-sm
                           hover:bg-indigo-700 disabled:opacity-60 disabled:cursor-not-allowed"
                >
                    <span x-show="!loading" x-text="primaryButtonLabel()"></span>
                    <span x-show="loading" class="flex items-center gap-2">
                        <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                            <circle class="opacity-25" cx="12" cy="12" r="10"
                                    stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor"
                                  d="M4 12a8 8 0 018-8v4l3.5-3.5L12 0v4a8 8 0 00-8 8h4z"></path>
                        </svg>
                        Processing…
                    </span>
                </button>
            </div>
        </div>
    </div>

    {{-- Step 2: details + card --}}
    <div x-show="step === 2" x-cloak>
        <form @submit.prevent="submitPayment" class="space-y-5">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold text-slate-900">
                    Complete your <span x-text="frequencyLabel()"></span> gift
                </h2>
                <p class="text-sm font-medium text-slate-700" x-text="summaryLabel()"></p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-slate-600">First name</label>
                    <input
                        x-model="donor.first_name"
                        type="text"
                        required
                        class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm
                               focus:border-indigo-500 focus:ring-indigo-500"
                    >
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600">Last name</label>
                    <input
                        x-model="donor.last_name"
                        type="text"
                        required
                        class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm
                               focus:border-indigo-500 focus:ring-indigo-500"
                    >
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-slate-600">Email</label>
                    <input
                        x-model="donor.email"
                        type="email"
                        required
                        class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm
                               focus:border-indigo-500 focus:ring-indigo-500"
                    >
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600">Phone</label>
                    <input
                        x-model="donor.phone"
                        type="tel"
                        class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm
                               focus:border-indigo-500 focus:ring-indigo-500"
                    >
                </div>
            </div>

            {{-- Basic address fields for saving to user profile --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-slate-600">Address line 1</label>
                    <input
                        x-model="donor.address_line1"
                        type="text"
                        class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm
                               focus:border-indigo-500 focus:ring-indigo-500"
                    >
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600">Address line 2</label>
                    <input
                        x-model="donor.address_line2"
                        type="text"
                        class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm
                               focus:border-indigo-500 focus:ring-indigo-500"
                    >
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-medium text-slate-600">City</label>
                    <input
                        x-model="donor.address_city"
                        type="text"
                        class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm
                               focus:border-indigo-500 focus:ring-indigo-500"
                    >
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600">State</label>
                    <input
                        x-model="donor.address_state"
                        type="text"
                        class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm
                               focus:border-indigo-500 focus:ring-indigo-500"
                    >
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600">Postal</label>
                    <input
                        x-model="donor.address_postal"
                        type="text"
                        class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm
                               focus:border-indigo-500 focus:ring-indigo-500"
                    >
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-600">Country</label>
                <input
                    x-model="donor.address_country"
                    type="text"
                    class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm
                           focus:border-indigo-500 focus:ring-indigo-500"
                >
            </div>

            {{-- Stripe card element --}}
            <div class="space-y-2">
                <label class="block text-xs font-medium text-slate-600">Payment details</label>

                <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                    <!-- Card number (big) -->
                    <div class="sm:col-span-2">
                        <div
                            id="card-number-element"
                            class="rounded-lg border border-slate-300 px-3 py-2"
                        ></div>
                    </div>

                    <!-- Expiry -->
                    <div>
                        <div
                            id="card-expiry-element"
                            class="rounded-lg border border-slate-300 px-3 py-2"
                        ></div>
                    </div>

                    <!-- CVC -->
                    <div>
                        <div
                            id="card-cvc-element"
                            class="rounded-lg border border-slate-300 px-3 py-2"
                        ></div>
                    </div>
                </div>

                <p class="text-xs text-red-500 mt-1" x-text="cardError"></p>
            </div>

            <button
                type="submit"
                :disabled="loading"
                class="inline-flex w-full items-center justify-center rounded-full bg-indigo-600
                       px-4 py-2.5 text-sm font-semibold text-white shadow-sm
                       hover:bg-indigo-700 disabled:opacity-60 disabled:cursor-not-allowed"
            >
                <span x-show="!loading" x-text="primaryButtonLabel()"></span>
                <span x-show="loading" class="flex items-center gap-2">
                    <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                        <circle class="opacity-25" cx="12" cy="12" r="10"
                                stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor"
                              d="M4 12a8 8 0 018-8v4l3.5-3.5L12 0v4a8 8 0 00-8 8h4z"></path>
                    </svg>
                    Processing…
                </span>
            </button>

            <button
                type="button"
                @click="step = 1"
                class="block w-full text-center text-xs text-slate-500 hover:text-slate-700 mt-1"
            >
                Back to change amount
            </button>
        </form>
    </template>
</div>

{{-- Stripe + Alpine controller (guard against multiple definition) --}}
<script src="https://js.stripe.com/v3/"></script>
<script>
    if (typeof window.donationWidget === 'undefined') {
        window.donationWidget = function (config) {
            return {
                step: 1,
                presets: [10, 25, 50, 100, 250, 500],
                frequency: 'one_time',
                amount: 25,
                customAmount: null,
                loading: false,

                // 'payment' for one-time, 'subscription' for monthly
                mode: null,
                transactionId: null,
                pledgeId: null,
                clientSecret: null,

                stripe: null,
                cardNumberElement: null,
                cardExpiryElement: null,
                cardCvcElement: null,
                cardError: '',


                donor: {
                    first_name: config.prefill.first_name || '',
                    last_name: config.prefill.last_name || '',
                    email: config.prefill.email || '',
                    phone: config.prefill.phone || '',
                    address_line1: config.prefill.address_line1 || '',
                    address_line2: config.prefill.address_line2 || '',
                    address_city: config.prefill.address_city || '',
                    address_state: config.prefill.address_state || '',
                    address_postal: config.prefill.address_postal || '',
                    address_country: config.prefill.address_country || '',
                },

                init() {
                    this.amount = this.presets[1] ?? 25;

                    this.stripe = Stripe(config.stripeKey);
                    const elements = this.stripe.elements();

                    // Create individual Elements
                    this.cardNumberElement = elements.create('cardNumber');
                    this.cardExpiryElement = elements.create('cardExpiry');
                    this.cardCvcElement    = elements.create('cardCvc');

                    // Mount them into the new containers
                    this.cardNumberElement.mount('#card-number-element');
                    this.cardExpiryElement.mount('#card-expiry-element');
                    this.cardCvcElement.mount('#card-cvc-element');

                    const updateError = (event) => {
                        this.cardError = event.error ? event.error.message : '';
                    };

                    this.cardNumberElement.on('change', updateError);
                    this.cardExpiryElement.on('change', updateError);
                    this.cardCvcElement.on('change', updateError);
                },

                totalAmount() {
                    return this.customAmount && this.customAmount > 0
                        ? this.customAmount
                        : this.amount;
                },

                selectPreset(value) {
                    this.amount = value;
                    this.customAmount = null;
                },

                summaryLabel() {
                    return `$${this.totalAmount()} ${this.frequency === 'monthly' ? 'per month' : 'one time'}`;
                },

                frequencyLabel() {
                    return this.frequency === 'monthly' ? 'monthly' : 'one-time';
                },

                primaryButtonLabel() {
                    return `Donate ${this.frequency === 'monthly' ? 'monthly' : 'one time'} $${this.totalAmount()}`;
                },

                async startDonation() {
                    this.loading = true;
                    this.cardError = '';

                    try {
                        const res = await fetch(config.startUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({
                                amount: this.totalAmount(),
                                frequency: this.frequency, // 'one_time' or 'monthly'
                            }),
                        });

                        if (!res.ok) throw new Error('Unable to start donation');

                        const data = await res.json();

                        // Now mode will be 'payment' (one-time) or 'subscription' (monthly)
                        this.mode          = data.mode;
                        this.clientSecret  = data.clientSecret;
                        this.transactionId = data.transactionId || null;
                        this.pledgeId      = data.pledgeId || null;

                        this.step = 2;
                    } catch (e) {
                        console.error(e);
                        alert('Something went wrong starting your donation.');
                    } finally {
                        this.loading = false;
                    }
                },

                async submitPayment() {
                    this.loading = true;
                    this.cardError = '';

                    const billingDetails = {
                        name: `${this.donor.first_name} ${this.donor.last_name}`.trim(),
                        email: this.donor.email,
                        phone: this.donor.phone,
                        address: {
                            line1: this.donor.address_line1 || undefined,
                            line2: this.donor.address_line2 || undefined,
                            city: this.donor.address_city || undefined,
                            state: this.donor.address_state || undefined,
                            postal_code: this.donor.address_postal || undefined,
                            country: this.donor.address_country || undefined,
                        },
                    };

                    try {
                        let result;

                        // Decide which Stripe API to call based on mode:
                        if (this.mode === 'payment') {
                            // One-time PaymentIntent
                            result = await this.stripe.confirmCardPayment(this.clientSecret, {
                                payment_method: {
                                    card: this.cardNumberElement,
                                    billing_details: billingDetails,
                                },
                            });
                        } else if (this.mode === 'subscription') {
                            // Monthly: SetupIntent to attach a payment method
                            result = await this.stripe.confirmCardSetup(this.clientSecret, {
                                payment_method: {
                                    card: this.cardNumberElement,
                                    billing_details: billingDetails,
                                },
                            });
                        } else {
                            throw new Error(`Unknown donation mode: ${this.mode}`);
                        }

                        if (result.error) {
                            this.cardError = result.error.message;
                            return;
                        }

                        const payload = {
                            // Now we just send the mode through directly
                            mode: this.mode, // 'payment' or 'subscription'

                            donor_first_name: this.donor.first_name,
                            donor_last_name: this.donor.last_name,
                            donor_email: this.donor.email,
                            donor_phone: this.donor.phone,
                            address_line1: this.donor.address_line1,
                            address_line2: this.donor.address_line2,
                            address_city: this.donor.address_city,
                            address_state: this.donor.address_state,
                            address_postal: this.donor.address_postal,
                            address_country: this.donor.address_country,
                        };

                        if (this.mode === 'payment') {
                            const pi = result.paymentIntent;
                            payload.transaction_id    = this.transactionId;
                            payload.payment_intent_id = pi.id;
                            payload.payment_method_id = pi.payment_method;
                            payload.charge_id         = pi.latest_charge || null;
                            payload.receipt_url       = pi.charges?.data?.[0]?.receipt_url ?? null;
                        } else if (this.mode === 'subscription') {
                            const si = result.setupIntent;
                            payload.pledge_id         = this.pledgeId;
                            payload.payment_method_id = si.payment_method;
                        }

                        const res = await fetch(config.completeUrl, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'Accept': 'text/html,application/json',
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: new URLSearchParams(payload),
                        });

                        if (res.redirected) {
                            window.location = res.url;
                        } else {
                            window.location.reload();
                        }
                    } catch (e) {
                        console.error(e);
                        this.cardError = 'Something went wrong confirming your payment.';
                    } finally {
                        this.loading = false;
                    }
                },
            };
        };
    }
</script>
