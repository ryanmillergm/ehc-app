@php
    $user           = auth()->user();
    $primaryAddress = $user?->primaryAddress;

    // Prefer the user's primary address country; otherwise default to US
    $prefillCountry = $primaryAddress?->country ?? 'US';
@endphp

<div
    id="donation-widget-root"
    wire:ignore.self
    x-data="donationWidget(@js([
        'startUrl'    => route('donations.start'),
        'completeUrl' => route('donations.complete'),
        'stripeKey'   => config('services.stripe.key'),
        'prefill'     => [
            'first_name'      => $user?->first_name ?? '',
            'last_name'       => $user?->last_name ?? '',
            'email'           => $user?->email ?? '',
            'phone'           => $primaryAddress?->phone ?? '',
            'address_line1'   => $primaryAddress?->line1 ?? '',
            'address_line2'   => $primaryAddress?->line2 ?? '',
            'address_city'    => $primaryAddress?->city ?? '',
            'address_state'   => $primaryAddress?->state ?? '',
            'address_postal'  => $primaryAddress?->postal_code ?? '',
            'address_country' => $prefillCountry,
        ],
        'countries' => $countries,
        'states'    => $states,
    ]))"
    x-init="if ($el.__donationWidgetInit) return; $el.__donationWidgetInit = true; init()"
    class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-6"
>
    {{-- Step 1: amount + frequency --}}
    <template x-if="step === 1">
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
    </template>

    {{-- Step 2: details + card --}}
    <template x-if="step === 2">
        <form @submit.prevent="submitPayment" class="space-y-5">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold text-slate-900">
                    Complete your <span x-text="frequencyLabel()"></span> gift
                </h2>
                <p class="text-sm font-medium text-slate-700" x-text="summaryLabel()"></p>
            </div>

            {{-- Donor basic info --}}
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

            {{-- Billing address --}}
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

            <div>
                <label class="block text-xs font-medium text-slate-600">Country</label>
                <select
                    x-ref="countrySelect"
                    x-model="donor.address_country"
                    class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm
                        focus:border-indigo-500 focus:ring-indigo-500 bg-white"
                >
                    <option value="">Select country</option>
                    <template x-for="country in countries" :key="country.code">
                        <option :value="country.code" x-text="country.name"></option>
                    </template>
                </select>
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

                {{-- State / Province (dynamic: select for US/CA, text for others) --}}
                <div>
                    <label class="block text-xs font-medium text-slate-600">State / Province</label>

                    <template x-if="statesForSelectedCountry()?.length">
                        <select
                            x-ref="stateSelect"
                            x-model="donor.address_state"
                            class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm
                                focus:border-indigo-500 focus:ring-indigo-500 bg-white"
                        >
                            <option value="">Select state</option>
                            <template x-for="state in statesForSelectedCountry()" :key="state.code">
                                <option :value="state.code" x-text="state.name"></option>
                            </template>
                        </select>
                    </template>

                    <template x-if="!statesForSelectedCountry()?.length">
                        <input
                            x-model="donor.address_state"
                            type="text"
                            class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm
                                focus:border-indigo-500 focus:ring-indigo-500"
                        >
                    </template>
                </div>


                <div>
                    <label class="block text-xs font-medium text-slate-600">Postal / ZIP</label>
                    <input
                        x-model="donor.address_postal"
                        type="text"
                        class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm
                               focus:border-indigo-500 focus:ring-indigo-500"
                    >
                </div>
            </div>

            {{-- Stripe card elements --}}
            <div class="space-y-3">
                <div>
                    <label class="block text-xs font-medium text-slate-600">Card number</label>
                    <div
                        id="card-number-element"
                        class="mt-1 rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    ></div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-600">Expiration</label>
                        <div
                            id="card-expiry-element"
                            class="mt-1 rounded-lg border border-slate-300 px-3 py-2 text-sm"
                        ></div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600">CVC</label>
                        <div
                            id="card-cvc-element"
                            class="mt-1 rounded-lg border border-slate-300 px-3 py-2 text-sm"
                        ></div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600">Postal / ZIP</label>
                        {{-- Mirrors billing postal code so it stays in sync --}}
                        <input
                            x-model="donor.address_postal"
                            type="text"
                            class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm
                                   focus:border-indigo-500 focus:ring-indigo-500"
                        >
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

@once
    {{-- Stripe.js --}}
    <script src="https://js.stripe.com/v3/"></script>

    <script>
        if (typeof window.donationWidget === 'undefined') {
            window.donationWidget = function (config) {
                console.log('[donationWidget] raw prefill:', config.prefill);

                console.log(
                    '[donationWidget] prefill country/state from PHP:',
                    config.prefill.address_country,
                    config.prefill.address_state
                );


                // Normalize prefill country → 2-letter code, default US
                let prefillCountry = (config.prefill.address_country || 'US').toString().trim();
                if (prefillCountry.length !== 2) {
                    prefillCountry = 'US';
                } else {
                    prefillCountry = prefillCountry.toUpperCase();
                }

                // Ensure US exists in countries list so the select can bind correctly
                if (!Array.isArray(config.countries)) {
                    config.countries = [];
                }
                if (!config.countries.find(c => c.code === 'US')) {
                    config.countries.unshift({ code: 'US', name: 'United States' });
                }

                console.log('[donationWidget] normalized prefillCountry:', prefillCountry);

                return {
                    step: 1,
                    presets: [10, 25, 50, 100, 250, 500],
                    frequency: 'one_time',
                    amount: 25,
                    customAmount: null,
                    loading: false,
                    mode: null,
                    transactionId: null,
                    pledgeId: null,
                    clientSecret: null,
                    stripe: null,
                    elements: null,
                    cardNumberElement: null,
                    cardExpiryElement: null,
                    cardCvcElement: null,
                    cardError: '',

                    countries: config.countries || [],
                    states: config.states || {},

                    donor: {
                        first_name:      config.prefill.first_name || '',
                        last_name:       config.prefill.last_name || '',
                        email:           config.prefill.email || '',
                        phone:           config.prefill.phone || '',
                        address_line1:   config.prefill.address_line1 || '',
                        address_line2:   config.prefill.address_line2 || '',
                        address_city:    config.prefill.address_city || '',
                        address_state:   config.prefill.address_state || '',
                        address_postal:  config.prefill.address_postal || '',
                        address_country: prefillCountry,
                    },

                    // --- helpers -------------------------------------------------

                    normalizeStateForCountry(countryCode, rawState) {
                        if (!rawState) return '';

                        const list = this.states[countryCode] || [];
                        if (!Array.isArray(list) || !list.length) {
                            return rawState;
                        }

                        const value = rawState.toString().trim();

                        // 1) exact code match (CO vs co)
                        const byCode = list.find(
                            s => s.code.toString().toUpperCase() === value.toUpperCase()
                        );
                        if (byCode) return byCode.code;

                        // 2) full name match (Colorado)
                        const byName = list.find(
                            s => s.name.toString().toLowerCase() === value.toLowerCase()
                        );
                        if (byName) return byName.code;

                        return value;
                    },

                    // --- lifecycle -----------------------------------------------

                    init() {
                        this.amount = this.presets[1] ?? 25;

                        // Normalize / default country on the instance
                        let c = (this.donor.address_country || '').trim();
                        if (c.length !== 2) {
                            c = 'US';
                        } else {
                            c = c.toUpperCase();
                        }
                        this.donor.address_country = c;

                        // Normalize state for this country (handles "Colorado" → "CO")
                        if (this.donor.address_state) {
                            this.donor.address_state = this.normalizeStateForCountry(
                                this.donor.address_country,
                                this.donor.address_state
                            );
                        }

                        console.log(
                            '[donationWidget] init country/state =',
                            this.donor.address_country,
                            this.donor.address_state
                        );

                        // Stripe Elements setup
                        this.stripe   = Stripe(config.stripeKey);
                        this.elements = this.stripe.elements();

                        // When we enter step 2, mount card elements and hard-sync the selects
                        this.$watch('step', (value) => {
                            if (value === 2) {
                                this.$nextTick(() => {
                                    this.mountCardElements();

                                    const countryCode = this.donor.address_country || '';
                                    const stateCode   = this.normalizeStateForCountry(
                                        countryCode,
                                        this.donor.address_state || ''
                                    );

                                    this.donor.address_country = countryCode;
                                    this.donor.address_state   = stateCode;

                                    if (this.$refs.countrySelect) {
                                        this.$refs.countrySelect.value = countryCode;
                                    }

                                    // stateSelect only exists when there are states for this country
                                    if (this.$refs.stateSelect) {
                                        this.$refs.stateSelect.value = stateCode;
                                    }

                                    console.log('[donationWidget] step 2 – synced selects', {
                                        donor_country: this.donor.address_country,
                                        donor_state:   this.donor.address_state,
                                        dom_country:   this.$refs.countrySelect && this.$refs.countrySelect.value,
                                        dom_state:     this.$refs.stateSelect && this.$refs.stateSelect.value,
                                    });
                                });
                            }
                        });
                    },


                    mountCardElements() {
                        if (this.cardNumberElement) return;

                        this.cardNumberElement = this.elements.create('cardNumber');
                        this.cardExpiryElement = this.elements.create('cardExpiry');
                        this.cardCvcElement    = this.elements.create('cardCvc');

                        this.cardNumberElement.mount('#card-number-element');
                        this.cardExpiryElement.mount('#card-expiry-element');
                        this.cardCvcElement.mount('#card-cvc-element');

                        const handleChange = (event) => {
                            this.cardError = event.error ? event.error.message : '';
                        };

                        this.cardNumberElement.on('change', handleChange);
                        this.cardExpiryElement.on('change', handleChange);
                        this.cardCvcElement.on('change', handleChange);
                    },

                    // --- UI helpers ----------------------------------------------

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

                    statesForSelectedCountry() {
                        const code = this.donor.address_country;
                        if (!code) return null;
                        return this.states[code] || null;
                    },

                    hasStates() {
                        const list = this.statesForSelectedCountry();
                        return Array.isArray(list) && list.length > 0;
                    },

                    // --- Stripe flow ---------------------------------------------

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
                                    frequency: this.frequency,
                                }),
                            });

                            if (!res.ok) throw new Error('Unable to start donation');

                            const data = await res.json();
                            this.mode          = data.mode;
                            this.clientSecret  = data.clientSecret;
                            this.transactionId = data.transactionId || null;
                            this.pledgeId      = data.pledgeId || null;

                            this.step = 2;
                        } catch (e) {
                            console.error('[donationWidget] startDonation error:', e);
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
                                line1:       this.donor.address_line1 || undefined,
                                line2:       this.donor.address_line2 || undefined,
                                city:        this.donor.address_city || undefined,
                                state:       this.donor.address_state || undefined,
                                postal_code: this.donor.address_postal || undefined,
                                country:     this.donor.address_country || undefined,
                            },
                        };

                        try {
                            let result;

                            if (this.mode === 'payment') {
                                result = await this.stripe.confirmCardPayment(this.clientSecret, {
                                    payment_method: {
                                        card: this.cardNumberElement,
                                        billing_details: billingDetails,
                                    },
                                });
                            } else {
                                result = await this.stripe.confirmCardSetup(this.clientSecret, {
                                    payment_method: {
                                        card: this.cardNumberElement,
                                        billing_details: billingDetails,
                                    },
                                });
                            }

                            if (result.error) {
                                console.error('[donationWidget] Stripe confirm error:', result.error);
                                this.cardError = result.error.message || 'Your card was declined.';
                                return;
                            }

                            const payload = {
                                mode: this.mode === 'payment' ? 'payment' : 'subscription',
                                donor_first_name: this.donor.first_name,
                                donor_last_name:  this.donor.last_name,
                                donor_email:      this.donor.email,
                                donor_phone:      this.donor.phone,
                                address_line1:    this.donor.address_line1,
                                address_line2:    this.donor.address_line2,
                                address_city:     this.donor.address_city,
                                address_state:    this.donor.address_state,
                                address_postal:   this.donor.address_postal,
                                address_country:  this.donor.address_country,
                            };

                            if (this.mode === 'payment') {
                                const pi = result.paymentIntent;
                                payload.transaction_id    = this.transactionId;
                                payload.payment_intent_id = pi.id;
                                payload.payment_method_id = pi.payment_method;
                                payload.charge_id         = pi.latest_charge || null;
                                payload.receipt_url       = pi.charges?.data?.[0]?.receipt_url ?? null;
                            } else {
                                const si = result.setupIntent;
                                payload.pledge_id         = this.pledgeId;
                                payload.payment_method_id = si.payment_method;
                            }

                            const params = new URLSearchParams();
                            Object.entries(payload).forEach(([key, value]) => {
                                if (value !== null && value !== undefined && value !== '') {
                                    params.append(key, value);
                                }
                            });

                            const res = await fetch(config.completeUrl, {
                                method: 'POST',
                                headers: {
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                    'Accept': 'application/json',
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                },
                                body: params,
                            });

                            const text = await res.text();
                            console.log('[donationWidget] complete status', res.status, 'redirect?', res.redirected);

                            if (res.redirected) {
                                window.location = res.url;
                                return;
                            }

                            if (!res.ok) {
                                console.error('[donationWidget] complete failed', res.status, text);
                                this.cardError = 'There was a problem finalizing your donation.';
                                return;
                            }

                            try {
                                const json = JSON.parse(text);
                                if (json.redirect) {
                                    window.location = json.redirect;
                                    return;
                                }
                            } catch (e) {
                                // not JSON
                            }

                            window.location.reload();
                        } catch (e) {
                            console.error('[donationWidget] submitPayment exception', e);
                            this.cardError = 'Something went wrong confirming your payment.';
                        } finally {
                            this.loading = false;
                        }
                    },
                };
            };
        }
    </script>
@endonce

