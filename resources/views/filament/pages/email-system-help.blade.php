<x-filament-panels::page>
    <div class="w-full space-y-8 px-4 lg:px-6">

        {{-- Page header --}}
        <header
            class="flex flex-col gap-4 rounded-2xl bg-gradient-to-r from-primary-600/10 via-primary-500/5 to-primary-600/10 p-6 ring-1 ring-primary-500/10"
        >
            <div class="flex items-start justify-between gap-4">
                <div class="space-y-2">
                    <div class="inline-flex items-center gap-2 rounded-full bg-white/70 px-3 py-1 text-xs font-medium text-primary-700 shadow-sm ring-1 ring-primary-500/20">
                        <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-primary-600 text-[0.625rem] font-semibold text-white">
                            i
                        </span>
                        Email System Help
                    </div>

                    <h1 class="text-2xl font-semibold text-gray-900">
                        Email System · Quick start &amp; troubleshooting
                    </h1>

                    <p class="max-w-2xl text-sm text-gray-700">
                        A quick workflow reference for creating lists, managing subscribers, and sending campaigns.
                        <span class="font-medium">Test sends</span> are instant (recommended).
                        <span class="font-medium">Live sends</span> require a queue worker.
                    </p>
                </div>

                <div class="hidden text-right text-xs text-gray-500 sm:block">
                    <div class="font-semibold text-gray-700">Quick reference</div>
                    <div>For admins &amp; staff</div>
                </div>
            </div>

            {{-- Step timeline --}}
            @php
                $steps = [
                    'Create a list',
                    'Add subscribers',
                    'Create a campaign',
                    'Send a test email',
                    'Send the live campaign',
                    'Run a queue worker',
                ];
            @endphp

            <ol class="mt-4 flex flex-wrap items-center gap-3 text-xs text-gray-700">
                @foreach ($steps as $index => $label)
                    <li class="flex items-center gap-2">
                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-primary-600 text-[0.7rem] font-semibold text-white shadow-sm">
                            {{ $index + 1 }}
                        </span>
                        <span class="font-medium">{{ $label }}</span>
                        @if (! $loop->last)
                            <span class="mx-1 h-px w-6 bg-primary-500/40 sm:w-10"></span>
                        @endif
                    </li>
                @endforeach
            </ol>
        </header>

        {{-- Quick goals + overview --}}
        <section class="grid gap-4 lg:grid-cols-2">
            <div class="rounded-2xl border border-gray-200/80 bg-white/80 p-5 shadow-sm shadow-gray-100">
                <h2 class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">
                    What you’re doing here
                </h2>
                <p class="mt-1 text-xs text-gray-500">
                    The basic flow from “idea” to “inbox”.
                </p>

                <ul class="mt-3 list-disc space-y-1.5 pl-4 text-sm text-gray-800">
                    <li>Create an Email List (audience / segment).</li>
                    <li>Add subscribers (who should receive messages).</li>
                    <li>Write a campaign (subject + HTML body).</li>
                    <li>Send a test email first.</li>
                    <li>Queue and send the live campaign.</li>
                </ul>
            </div>

            <div class="rounded-2xl border border-gray-200/80 bg-white/80 p-5 shadow-sm shadow-gray-100">
                <h2 class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">
                    Key concepts
                </h2>
                <p class="mt-1 text-xs text-gray-500">
                    Useful mental model for how the system behaves.
                </p>

                <dl class="mt-3 space-y-3 text-sm text-gray-800">
                    <div class="flex items-start justify-between gap-2">
                        <dt class="font-semibold text-gray-900">Email List</dt>
                        <dd class="max-w-xs text-right text-gray-600">
                            A segment (e.g. <code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs">newsletter</code>).
                        </dd>
                    </div>
                    <div class="flex items-start justify-between gap-2">
                        <dt class="font-semibold text-gray-900">Subscriber</dt>
                        <dd class="max-w-xs text-right text-gray-600">
                            A person with an email address and opt-in/out state.
                        </dd>
                    </div>
                    <div class="flex items-start justify-between gap-2">
                        <dt class="font-semibold text-gray-900">Campaign</dt>
                        <dd class="max-w-xs text-right text-gray-600">
                            The message content + the chosen list.
                        </dd>
                    </div>
                    <div class="flex items-start justify-between gap-2">
                        <dt class="font-semibold text-gray-900">Test vs Live</dt>
                        <dd class="max-w-xs text-right text-gray-600">
                            Test sends one email now; live sends to the whole list via queue.
                        </dd>
                    </div>
                </dl>
            </div>
        </section>

        {{-- Step 1 --}}
        <section class="rounded-2xl border border-gray-200/80 bg-white p-5 shadow-sm shadow-gray-100 space-y-3">
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-sm font-semibold tracking-tight text-gray-900">
                    Step 1 · Create a list
                </h2>
                <span class="rounded-full bg-primary-50 px-2.5 py-1 text-[0.7rem] font-medium text-primary-700 ring-1 ring-primary-100">
                    Email Lists
                </span>
            </div>

            <p class="text-sm text-gray-700">
                Create an Email List (usually <span class="font-semibold">Marketing</span>) and give it a stable key.
                Example:
                <code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs">newsletter</code>.
            </p>

            <div class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2.5 text-xs text-amber-900">
                <span class="font-semibold">Tip:</span>
                Use a key that won’t change. Labels can be pretty — keys should be boring and reliable.
            </div>
        </section>

        {{-- Step 2 --}}
        <section class="rounded-2xl border border-gray-200/80 bg-white p-5 shadow-sm shadow-gray-100 space-y-3">
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-sm font-semibold tracking-tight text-gray-900">
                    Step 2 · Add subscribers
                </h2>
                <span class="rounded-full bg-primary-50 px-2.5 py-1 text-[0.7rem] font-medium text-primary-700 ring-1 ring-primary-100">
                    Subscribers
                </span>
            </div>

            <p class="text-sm text-gray-700">
                Open the list and use the <span class="font-semibold">Add subscriber</span> button.
                Subscribers can be unsubscribed per-list.
            </p>

            <ul class="list-disc space-y-1.5 pl-4 text-sm text-gray-800">
                <li>Active subscribers will receive live sends.</li>
                <li>Unsubscribed people are skipped automatically.</li>
                <li>Use test sends to verify templates before blasting everyone.</li>
            </ul>
        </section>

        {{-- Step 3 --}}
        <section class="rounded-2xl border border-gray-200/80 bg-white p-5 shadow-sm shadow-gray-100 space-y-3">
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-sm font-semibold tracking-tight text-gray-900">
                    Step 3 · Create a campaign
                </h2>
                <span class="rounded-full bg-primary-50 px-2.5 py-1 text-[0.7rem] font-medium text-primary-700 ring-1 ring-primary-100">
                    Email Campaigns
                </span>
            </div>

            <p class="text-sm text-gray-700">
                Create an Email Campaign, pick the Email List, write a subject + body.
            </p>

            <ul class="list-disc space-y-1.5 pl-4 text-sm text-gray-800">
                <li><span class="font-semibold">Subject</span> should be short and human.</li>
                <li><span class="font-semibold">Body</span> is HTML — keep it email-client friendly.</li>
            </ul>

            <div class="rounded-xl border border-sky-200 bg-sky-50 px-3 py-2.5 text-xs text-sky-900">
                <span class="font-semibold">Suggestion:</span>
                Put your “real send” behind a test send habit. It saves reputations.
            </div>
        </section>

        {{-- Step 4 --}}
        <section class="rounded-2xl border border-gray-200/80 bg-white p-5 shadow-sm shadow-gray-100 space-y-3">
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-sm font-semibold tracking-tight text-gray-900">
                    Step 4 · Send a test email
                </h2>
                <span class="rounded-full bg-primary-50 px-2.5 py-1 text-[0.7rem] font-medium text-primary-700 ring-1 ring-primary-100">
                    Recommended
                </span>
            </div>

            <p class="text-sm text-gray-700">
                Open the campaign and click <strong>Send test</strong> to send to a single address.
            </p>

            <div class="rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-xs text-gray-700">
                <span class="font-semibold">What this verifies:</span>
                mail config, template rendering, links, images, and “does this look weird in Gmail”.
            </div>
        </section>

        {{-- Step 5 --}}
        <section class="rounded-2xl border border-gray-200/80 bg-white p-5 shadow-sm shadow-gray-100 space-y-3">
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-sm font-semibold tracking-tight text-gray-900">
                    Step 5 · Send the live campaign
                </h2>
                <span class="rounded-full bg-primary-50 px-2.5 py-1 text-[0.7rem] font-medium text-primary-700 ring-1 ring-primary-100">
                    Queued
                </span>
            </div>

            <p class="text-sm text-gray-700">
                Click <strong>Send</strong>. You will see a confirmation modal before anything is queued.
            </p>

            <div class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2.5 text-xs text-amber-900">
                <span class="font-semibold">Heads up:</span>
                Live sends are processed via the queue. If the worker isn’t running, it’ll look like “nothing happened”
                (because the jobs are just sitting there patiently… judging you silently).
            </div>
        </section>

        {{-- Step 6 --}}
        <section class="rounded-2xl border border-gray-200/80 bg-white p-5 shadow-sm shadow-gray-100 space-y-3">
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-sm font-semibold tracking-tight text-gray-900">
                    Step 6 · Queue worker requirement
                </h2>
                <span class="rounded-full bg-primary-50 px-2.5 py-1 text-[0.7rem] font-medium text-primary-700 ring-1 ring-primary-100">
                    Infrastructure
                </span>
            </div>

            <p class="text-sm text-gray-700">
                Live sends use the queue. Ensure a worker is running:
            </p>

            <div class="rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-xs text-gray-800">
                <code class="block whitespace-pre-wrap">php artisan queue:work</code>
            </div>

            <p class="text-xs text-gray-500">
                In production, you’ll typically use a process manager (or Horizon) to keep workers running.
            </p>
        </section>

        {{-- Troubleshooting --}}
        <section class="rounded-2xl border border-dashed border-gray-300 bg-gray-50/90 p-5 space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-900">
                    Troubleshooting checklist
                </h2>
                <span class="text-xs text-gray-500">Most issues are mail config or queue worker</span>
            </div>

            <ul class="space-y-2 text-sm text-gray-800">
                <li class="rounded-xl border border-gray-200 bg-white px-3 py-2">
                    <span class="font-semibold">Nothing sends:</span>
                    confirm your mail driver config and that a queue worker is running for live sends.
                </li>
                <li class="rounded-xl border border-gray-200 bg-white px-3 py-2">
                    <span class="font-semibold">Some users are skipped:</span>
                    they may be globally unsubscribed or unsubscribed from that list.
                </li>
                <li class="rounded-xl border border-gray-200 bg-white px-3 py-2">
                    <span class="font-semibold">Test sends work but live doesn’t:</span>
                    almost always the worker isn’t running (or queue connection differs between envs).
                </li>
            </ul>

            <div class="rounded-xl border border-sky-200 bg-sky-50 px-3 py-2.5 text-xs text-sky-900">
                <span class="font-semibold">Local dev trick:</span>
                If you want live sends to run immediately (no worker) you can temporarily use
                <code class="rounded bg-white/70 px-1.5 py-0.5 text-[0.7rem]">QUEUE_CONNECTION=sync</code>.
                (Just don’t leave it that way in production.)
            </div>
        </section>

    </div>
</x-filament-panels::page>
