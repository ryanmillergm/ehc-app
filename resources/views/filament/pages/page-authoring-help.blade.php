<x-filament-panels::page>
    @php
        $steps = [
            'Choose render mode',
            'Pick template or blocks',
            'Configure hero media',
            'Attach images/videos by role',
            'Publish and QA',
        ];

        $pageTranslationsUrl = \App\Filament\Resources\PageTranslationResource::getUrl('index');
        $videoHelpUrl = \App\Filament\Pages\VideoSystemHelp::getUrl();
    @endphp

    <div class="w-full space-y-8 px-4 lg:px-6">
        <header class="flex flex-col gap-4 rounded-2xl bg-gradient-to-r from-primary-600/10 via-primary-500/5 to-primary-600/10 p-6 ring-1 ring-primary-500/10">
            <div class="flex items-start justify-between gap-4">
                <div class="space-y-2">
                    <div class="inline-flex items-center gap-2 rounded-full bg-white/70 px-3 py-1 text-xs font-medium text-primary-700 shadow-sm ring-1 ring-primary-500/20">
                        <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-primary-600 text-[0.625rem] font-semibold text-white">
                            i
                        </span>
                        Page Authoring Help
                    </div>

                    <h1 class="text-2xl font-semibold text-gray-900">
                        Page Templates, Blocks, and Hero Media
                    </h1>

                    <p class="max-w-3xl text-sm text-gray-700">
                        Operational guide for editors building public Page Translation records with templates, block sections, custom HTML, and hero media.
                    </p>
                </div>

                <div class="hidden text-right text-xs text-gray-500 sm:block">
                    <div class="font-semibold text-gray-700">Quick reference</div>
                    <div>For admins &amp; editors</div>
                </div>
            </div>

            <ol class="mt-2 flex flex-wrap items-center gap-3 text-xs text-gray-700">
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

        <section class="grid gap-4 lg:grid-cols-3">
            <div class="rounded-2xl border border-gray-200/80 bg-white/80 p-5 shadow-sm shadow-gray-100">
                <h2 class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Template</h2>
                <p class="mt-3 text-sm text-gray-800">
                    Curated layouts for polished pages. Choose this for fast publishing with consistent design.
                </p>
            </div>

            <div class="rounded-2xl border border-gray-200/80 bg-white/80 p-5 shadow-sm shadow-gray-100">
                <h2 class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Block Builder</h2>
                <p class="mt-3 text-sm text-gray-800">
                    Flexible section-based pages using reusable content blocks. Structured blocks use plus-button repeaters, and gallery uploads create reusable Image records.
                </p>
            </div>

            <div class="rounded-2xl border border-gray-200/80 bg-white/80 p-5 shadow-sm shadow-gray-100">
                <h2 class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Custom HTML</h2>
                <p class="mt-3 text-sm text-gray-800">
                    Advanced mode for handcrafted markup. HTML is sanitized on save, and scripts are not allowed.
                </p>
            </div>
        </section>

        <section class="rounded-2xl border border-gray-200/80 bg-white p-5 shadow-sm shadow-gray-100 space-y-3">
            <h2 class="text-sm font-semibold tracking-tight text-gray-900">Template Choices</h2>
            <div class="grid gap-3 lg:grid-cols-2">
                <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-800">
                    <span class="font-semibold text-gray-900">Standard</span>: guidebook layout for evergreen information, service details, and general pages.
                </div>
                <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-800">
                    <span class="font-semibold text-gray-900">Campaign</span>: action landing layout for donation, volunteer, and urgent response pages.
                </div>
                <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-800">
                    <span class="font-semibold text-gray-900">Story</span>: longform journal layout for testimonies, updates, and reflective ministry stories.
                </div>
                <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-800">
                    <span class="font-semibold text-gray-900">Immersive</span>: cinematic layout for media-led feature pages where the hero image or video carries the first impression.
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-gray-200/80 bg-white p-5 shadow-sm shadow-gray-100 space-y-3">
            <h2 class="text-sm font-semibold tracking-tight text-gray-900">Hero Controls</h2>
            <ul class="list-disc space-y-1.5 pl-4 text-sm text-gray-800">
                <li><code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs">Hero Mode</code>: chooses none, image, slider, or video.</li>
                <li><code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs">Hero Style</code>: contained keeps media inside the page width; full bleed spans the viewport.</li>
                <li><code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs">Hero Height</code>: 70vh, 80vh, or 100vh layout intent.</li>
                <li><code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs">Overlay</code>, text alignment, and text width control hero readability and composition.</li>
                <li>Hero title, subtitle, CTA text, and CTA URL override the default page title/description in template heroes.</li>
            </ul>
        </section>

        <section class="rounded-2xl border border-gray-200/80 bg-white p-5 shadow-sm shadow-gray-100 space-y-3">
            <h2 class="text-sm font-semibold tracking-tight text-gray-900">Hero Media Rules</h2>
            <ul class="list-disc space-y-1.5 pl-4 text-sm text-gray-800">
                <li><span class="font-semibold">Image mode</span> uses the Page Translation image relationship with the Header role.</li>
                <li><span class="font-semibold">Slider mode</span> uses an active hero slider image group; if no slides exist, it falls back to the header image.</li>
                <li><span class="font-semibold">Hero Video</span> mode uses an active Video Relationship with target type Page Translation and role Hero Video.</li>
                <li>If Hero Video is missing, the video resolver can use Featured Video. If no video resolves, the page falls back to the header image when one exists.</li>
                <li>For translated pages, media may fall back to the default language translation when the current translation has no matching assignment.</li>
                <li><span class="font-semibold">Block Builder hero blocks</span> use the same Page Translation media relationships. Choose Header Image, Hero Video, or Hero Slider inside the hero block.</li>
            </ul>

            <div class="rounded-xl border border-sky-200 bg-sky-50 px-3 py-2.5 text-xs text-sky-900">
                <span class="font-semibold">Important:</span>
                setting Hero Mode to Video does not upload or attach a video. Create the Video record, then create a Video Relationship for the Page Translation.
            </div>

            <div class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2.5 text-xs text-amber-900">
                <span class="font-semibold">YouTube/Vimeo hero embeds are best-effort backgrounds.</span>
                Uploaded MP4/WebM videos are preferred when you need a clean hero without provider branding, title overlays, or iframe behavior.
            </div>
        </section>

        <section class="rounded-2xl border border-gray-200/80 bg-white p-5 shadow-sm shadow-gray-100 space-y-3">
            <h2 class="text-sm font-semibold tracking-tight text-gray-900">Block Builder Fields</h2>
            <ul class="list-disc space-y-1.5 pl-4 text-sm text-gray-800">
                <li>Gallery, stats, testimonials, timeline, pricing, feature grid, and icon list blocks use repeaters. Click the add button to add another item.</li>
                <li>Gallery items can use an existing Image record, upload a new image, or render a direct source URL.</li>
                <li>Uploaded gallery images are saved as reusable Image records, the same as images created through the Images resource.</li>
                <li>JSON entry is no longer part of the block-builder workflow.</li>
            </ul>
        </section>

        <section class="rounded-2xl border border-gray-200/80 bg-white p-5 shadow-sm shadow-gray-100 space-y-3">
            <h2 class="text-sm font-semibold tracking-tight text-gray-900">Hero Video Workflow</h2>
            <ol class="list-decimal space-y-1.5 pl-5 text-sm text-gray-800">
                <li>Create or choose an active Video record.</li>
                <li>For YouTube/Vimeo, paste the embed URL only. Do not paste full iframe HTML.</li>
                <li>For the cleanest hero background, use an uploaded or CDN-hosted MP4/WebM instead of an embed.</li>
                <li>Create an active Video Relationship.</li>
                <li>Set target type to <span class="font-semibold">Page Translation</span> and select the related translation record.</li>
                <li>Set role to <span class="font-semibold">Hero Video</span>.</li>
                <li>Set the Page Translation Hero Mode to <span class="font-semibold">Video</span>, then verify the public page.</li>
            </ol>
        </section>

        <section class="rounded-2xl border border-dashed border-gray-300 bg-gray-50/90 p-5 space-y-4">
            <h2 class="text-sm font-semibold text-gray-900">Troubleshooting</h2>
            <div class="grid gap-3 lg:grid-cols-2">
                <div class="rounded-xl border border-gray-200 bg-white px-4 py-3">
                    <div class="font-semibold text-gray-900">Video is not showing</div>
                    <ul class="mt-2 list-disc space-y-1.5 pl-4 text-sm text-gray-800">
                        <li>Check that both the Video and Video Relationship are active.</li>
                        <li>Confirm the relationship target is the correct Page Translation.</li>
                        <li>Confirm Hero Mode is set to Video.</li>
                    </ul>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white px-4 py-3">
                    <div class="font-semibold text-gray-900">Image shows instead of video</div>
                    <ul class="mt-2 list-disc space-y-1.5 pl-4 text-sm text-gray-800">
                        <li>The page falls back to header image when no active hero or featured video resolves.</li>
                        <li>Add a Hero Video relationship directly to avoid relying on fallback.</li>
                    </ul>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white px-4 py-3">
                    <div class="font-semibold text-gray-900">Template is not changing</div>
                    <ul class="mt-2 list-disc space-y-1.5 pl-4 text-sm text-gray-800">
                        <li>Confirm Render Mode is Template.</li>
                        <li>Unknown template values fall back to Standard.</li>
                    </ul>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white px-4 py-3">
                    <div class="font-semibold text-gray-900">Custom HTML is stripped</div>
                    <ul class="mt-2 list-disc space-y-1.5 pl-4 text-sm text-gray-800">
                        <li>Scripts and event handlers are removed on save.</li>
                        <li>Trusted custom HTML still blocks scripts.</li>
                    </ul>
                </div>
            </div>
        </section>

        <section class="flex flex-col gap-3 rounded-2xl border border-gray-200/80 bg-white p-5 shadow-sm shadow-gray-100 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-sm font-semibold tracking-tight text-gray-900">Related Admin Areas</h2>
                <p class="mt-1 text-sm text-gray-700">
                    Use Page Translations for page copy and hero settings. Use Video System Help for deeper video assignment rules.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <a href="{{ $pageTranslationsUrl }}" class="inline-flex items-center justify-center rounded-lg bg-primary-600 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-primary-500">
                    Page Translations
                </a>
                <a href="{{ $videoHelpUrl }}" class="inline-flex items-center justify-center rounded-lg bg-gray-100 px-3 py-2 text-xs font-semibold text-gray-900 ring-1 ring-gray-200 hover:bg-gray-50">
                    Video System Help
                </a>
            </div>
        </section>
    </div>
</x-filament-panels::page>
