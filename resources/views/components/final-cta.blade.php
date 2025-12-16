@props([
    'giveHref' => '#give-form',
    'volunteerHref' => url('/#serve'),
    'visitHref' => url('/#visit'),
])

<section {{ $attributes->class(['bg-gradient-to-r from-rose-800 to-slate-900']) }}>
    <div class="mx-auto max-w-screen-2xl px-6 sm:px-8 lg:px-12 2xl:px-20 py-14 sm:py-16">
        <div class="rounded-3xl border border-white/20 bg-slate-950/90 backdrop-blur-md p-6 sm:p-8">
            <div class="flex flex-col gap-6 md:flex-row md:items-center md:justify-between">
                <div>
                    <div class="inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-1.5 text-xs font-semibold text-white/85 ring-1 ring-white/10">
                        Church Without Walls • Sacramento
                        <span class="h-1.5 w-1.5 rounded-full bg-rose-400"></span>
                        Thu + Sun 11am
                    </div>

                    <h3 class="mt-3 text-white text-2xl sm:text-3xl font-extrabold tracking-tight">
                        Be part of someone’s next step.
                    </h3>

                    <p class="mt-2 max-w-2xl text-white/70">
                        Give today, serve this week, or visit in person — your presence and generosity help change real lives.
                    </p>
                </div>

                <div class="flex flex-col sm:flex-row gap-3">
                    <a href="{{ $giveHref }}"
                       class="inline-flex items-center justify-center rounded-full bg-rose-600 px-7 py-3.5 text-sm font-semibold text-white shadow-sm hover:bg-rose-500 transition">
                        Give now
                    </a>

                    <a href="{{ $volunteerHref }}"
                       class="inline-flex items-center justify-center rounded-full bg-white/10 px-7 py-3.5 text-sm font-semibold text-white ring-1 ring-white/15 hover:bg-white/15 transition">
                        Volunteer
                    </a>

                    <a href="{{ $visitHref }}"
                       class="inline-flex items-center justify-center rounded-full px-7 py-3.5 text-sm font-semibold text-white/80 hover:text-white transition">
                        Visit →
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>
