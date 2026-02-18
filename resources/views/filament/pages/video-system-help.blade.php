<x-filament-panels::page>
    @php
        $steps = [
            'Create or choose a Video',
            'Create a Video Relationship',
            'Select target type + related record',
            'Set the role (Hero/Featured/Inline)',
            'Set active flags and validate rendering',
        ];
    @endphp

    <div class="w-full space-y-8 px-4 lg:px-6">
        <header class="flex flex-col gap-4 rounded-2xl bg-gradient-to-r from-primary-600/10 via-primary-500/5 to-primary-600/10 p-6 ring-1 ring-primary-500/10">
            <div class="flex items-start justify-between gap-4">
                <div class="space-y-2">
                    <div class="inline-flex items-center gap-2 rounded-full bg-white/70 px-3 py-1 text-xs font-medium text-primary-700 shadow-sm ring-1 ring-primary-500/20">
                        <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-primary-600 text-[0.625rem] font-semibold text-white">
                            i
                        </span>
                        Video System Help
                    </div>

                    <h1 class="text-2xl font-semibold text-gray-900">
                        Videos and Video Relationships
                    </h1>

                    <p class="max-w-3xl text-sm text-gray-700">
                        Operational guide for managing uploaded/embed video records and assigning them to content targets.
                        This covers <span class="font-semibold">Page Translation</span> and <span class="font-semibold">Home Page Content</span> targets.
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

        <section class="grid gap-4 lg:grid-cols-2">
            <div class="rounded-2xl border border-gray-200/80 bg-white/80 p-5 shadow-sm shadow-gray-100">
                <h2 class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Resources</h2>
                <ul class="mt-3 list-disc space-y-1.5 pl-4 text-sm text-gray-800">
                    <li><span class="font-semibold">Videos</span>: source file/embed metadata and activation status.</li>
                    <li><span class="font-semibold">Video Relationships</span>: polymorphic assignment to target content with role + sort order.</li>
                </ul>
            </div>

            <div class="rounded-2xl border border-gray-200/80 bg-white/80 p-5 shadow-sm shadow-gray-100">
                <h2 class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Target Types</h2>
                <ul class="mt-3 list-disc space-y-1.5 pl-4 text-sm text-gray-800">
                    <li><span class="font-semibold">Page Translation</span>: page-level hero/header or inline video slots.</li>
                    <li><span class="font-semibold">Home Page Content</span>: homepage content-level video assignment targets.</li>
                </ul>
            </div>
        </section>

        <section class="rounded-2xl border border-gray-200/80 bg-white p-5 shadow-sm shadow-gray-100 space-y-3">
            <h2 class="text-sm font-semibold tracking-tight text-gray-900">Role Rules</h2>
            <ul class="list-disc space-y-1.5 pl-4 text-sm text-gray-800">
                <li><code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs">Hero Video</code>: primary role for page header background video.</li>
                <li><code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs">Featured Video</code>: fallback role when Hero Video assignment is missing.</li>
                <li><code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs">Inline Video</code>: non-hero content slots.</li>
            </ul>

            <div class="rounded-xl border border-sky-200 bg-sky-50 px-3 py-2.5 text-xs text-sky-900">
                <span class="font-semibold">For page header background video:</span>
                set target type to <span class="font-semibold">Page Translation</span>, select that translation record, and set role to <span class="font-semibold">Hero Video</span>.
                If Hero Video is not assigned, the resolver falls back to Featured Video.
            </div>
        </section>

        <section class="rounded-2xl border border-gray-200/80 bg-white p-5 shadow-sm shadow-gray-100 space-y-3">
            <h2 class="text-sm font-semibold tracking-tight text-gray-900">Authoring Workflow</h2>
            <ol class="list-decimal space-y-1.5 pl-5 text-sm text-gray-800">
                <li>Create a Video record (uploaded file or embed URL source).</li>
                <li>Open Video Relationships and create a new assignment.</li>
                <li>Select the video, target type, and related record ID.</li>
                <li>Choose role based on placement intent (Hero/Featured/Inline).</li>
                <li>Set <code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs">is_active</code> on both Video and Video Relationship.</li>
                <li>Verify the target page content mode supports video rendering (for page hero, use hero video mode).</li>
            </ol>
        </section>

        <section class="rounded-2xl border border-dashed border-gray-300 bg-gray-50/90 p-5 space-y-4">
            <h2 class="text-sm font-semibold text-gray-900">Troubleshooting</h2>
            <div class="grid gap-3 lg:grid-cols-2">
                <div class="rounded-xl border border-gray-200 bg-white px-4 py-3">
                    <div class="font-semibold text-gray-900">Video is not showing</div>
                    <ul class="mt-2 list-disc space-y-1.5 pl-4 text-sm text-gray-800">
                        <li>Check both video and relationship are active.</li>
                        <li>Confirm target type and related record are correct.</li>
                        <li>Confirm expected role is assigned (Hero or Featured fallback).</li>
                    </ul>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white px-4 py-3">
                    <div class="font-semibold text-gray-900">Header video still falls back</div>
                    <ul class="mt-2 list-disc space-y-1.5 pl-4 text-sm text-gray-800">
                        <li>Confirm hero mode is set to video on the page translation.</li>
                        <li>Add Hero Video assignment directly to avoid relying on fallback.</li>
                    </ul>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white px-4 py-3">
                    <div class="font-semibold text-gray-900">Upload accepted but playback fails</div>
                    <ul class="mt-2 list-disc space-y-1.5 pl-4 text-sm text-gray-800">
                        <li>Use MP4/WebM files and verify valid public URL/path metadata.</li>
                        <li>Try a smaller optimized file for hero/background usage.</li>
                    </ul>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white px-4 py-3">
                    <div class="font-semibold text-gray-900">Wrong locale/page shows another video</div>
                    <ul class="mt-2 list-disc space-y-1.5 pl-4 text-sm text-gray-800">
                        <li>Verify assignment is attached to the intended Page Translation record.</li>
                        <li>Remember translation fallback can choose default language records.</li>
                    </ul>
                </div>
            </div>
        </section>
    </div>
</x-filament-panels::page>
