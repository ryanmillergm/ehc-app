@extends('layouts.app')

@section('content')
<div class="max-w-2xl mx-auto p-6">
    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-6 space-y-4">
        <h1 class="text-lg font-semibold text-slate-900">Stripe Webhook Status (Local)</h1>

        <div class="space-y-2 text-sm">
            <div class="flex items-center justify-between">
                <span class="text-slate-600">APP_URL</span>
                <span class="font-mono text-slate-900">{{ $appUrl }}</span>
            </div>

            <div class="flex items-center justify-between">
                <span class="text-slate-600">STRIPE_WEBHOOK_SECRET set?</span>
                <span class="{{ $webhookSecretSet ? 'text-emerald-700' : 'text-rose-700' }} font-semibold">
                    {{ $webhookSecretSet ? 'YES' : 'NO' }}
                </span>
            </div>

            <div class="flex items-center justify-between">
                <span class="text-slate-600">Last webhook hit</span>
                <span class="font-mono text-slate-900">
                    {{ $lastHitAt ?: 'Never (yet)' }}
                </span>
            </div>
        </div>

        <div class="text-xs text-slate-500 leading-relaxed">
            Tip: run <span class="font-mono">stripe listen --forward-to {{ rtrim($appUrl, '/') }}/stripe/webhook</span>
            and then trigger a test event from Stripe CLI.
        </div>
    </div>
</div>
@endsection
