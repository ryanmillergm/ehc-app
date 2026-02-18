<x-filament-panels::page>
    @php
        $emailHelpUrl = \App\Filament\Pages\EmailSystemHelp::getUrl();
        $homeSectionsHelpUrl = \App\Filament\Pages\HomeSectionsDocumentation::getUrl();
        $videoHelpUrl = \App\Filament\Pages\VideoSystemHelp::getUrl();

        // $donationsHelpUrl = \App\Filament\Pages\DonationsSystemHelp::getUrl();

        $nav = [
            ['id' => 'overview', 'label' => 'Overview'],
            ['id' => 'dashboard', 'label' => 'Dashboard'],
            ['id' => 'users-roles-perms', 'label' => 'Users / Roles / Permissions'],
            ['id' => 'teams', 'label' => 'Teams / Tenancy'],
            ['id' => 'addresses', 'label' => 'Addresses'],
            ['id' => 'pages', 'label' => 'Pages & Translations'],
            ['id' => 'homepage-cms', 'label' => 'Homepage CMS'],
            ['id' => 'media-library', 'label' => 'Media Library'],
            ['id' => 'seo-management', 'label' => 'SEO Management'],
            ['id' => 'seed-data', 'label' => 'Seed Data / Recovery'],
            ['id' => 'cms-troubleshooting', 'label' => 'CMS Troubleshooting'],
            ['id' => 'email-system', 'label' => 'Email System'],
            ['id' => 'email-workflows', 'label' => 'Email Workflows'],
            ['id' => 'email-queue', 'label' => 'Email Queue Pipeline'],
            ['id' => 'email-troubleshooting', 'label' => 'Email Troubleshooting'],
            ['id' => 'donations', 'label' => 'Donations'],
            ['id' => 'transactions', 'label' => 'Transactions'],
            ['id' => 'pledges', 'label' => 'Pledges (Recurring)'],
            ['id' => 'refunds', 'label' => 'Refunds'],
            ['id' => 'donations-troubleshooting', 'label' => 'Donations Troubleshooting'],
            ['id' => 'ops', 'label' => 'Operations & Playbooks'],
            ['id' => 'testing', 'label' => 'Testing & Maintenance'],
        ];

        $sectionBadge = fn (string $text) => "<span class=\"rounded-full bg-primary-50 px-2.5 py-1 text-[0.7rem] font-medium text-primary-700 ring-1 ring-primary-100\">{$text}</span>";

        $callout = function (string $tone, string $title, string $body) {
            $map = [
                'info' => ['border-sky-200', 'bg-sky-50', 'text-sky-900'],
                'warn' => ['border-amber-200', 'bg-amber-50', 'text-amber-900'],
                'danger' => ['border-rose-200', 'bg-rose-50', 'text-rose-900'],
                'muted' => ['border-gray-200', 'bg-gray-50', 'text-gray-800'],
            ];
            [$b, $bg, $t] = $map[$tone] ?? $map['info'];

            return <<<HTML
<div class="rounded-xl border {$b} {$bg} px-3 py-2.5 text-xs {$t}">
    <div class="font-semibold">{$title}</div>
    <div class="mt-1 leading-relaxed">{$body}</div>
</div>
HTML;
        };
    @endphp

    <div
        class="w-full space-y-8 px-4 lg:px-6"
        x-data="{
            jump(id) {
                const el = document.getElementById(id);
                if (!el) return;
                el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                history.replaceState(null, '', `#${id}`);
            }
        }"
    >
        {{-- Page header --}}
        <header class="flex flex-col gap-4 rounded-2xl bg-gradient-to-r from-primary-600/10 via-primary-500/5 to-primary-600/10 p-6 ring-1 ring-primary-500/10">
            <div class="flex items-start justify-between gap-4">
                <div class="space-y-2">
                    <div class="inline-flex items-center gap-2 rounded-full bg-white/70 px-3 py-1 text-xs font-medium text-primary-700 shadow-sm ring-1 ring-primary-500/20">
                        <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-primary-600 text-[0.625rem] font-semibold text-white">
                            i
                        </span>
                        Admin documentation
                    </div>

                    <h1 class="text-2xl font-semibold text-gray-900">
                        Admin Documentation
                    </h1>

                    <p class="max-w-3xl text-sm text-gray-700">
                        Internal guide for operating this Filament admin panel:
                        <span class="font-medium">users & access</span>, <span class="font-medium">content</span>,
                        <span class="font-medium">email marketing</span>, and <span class="font-medium">donations</span>.
                    </p>
                </div>

                <div class="hidden text-right text-xs text-gray-500 sm:block">
                    <div class="font-semibold text-gray-700">Admin-only</div>
                    <div>How to run the system</div>
                </div>
            </div>

            {{-- Jump to section --}}
            <div class="flex flex-wrap gap-2">
                @foreach ($nav as $item)
                    <button
                        type="button"
                        class="rounded-full bg-white/70 px-3 py-1 text-xs font-medium text-gray-800 ring-1 ring-gray-200 hover:bg-white"
                        x-on:click="jump('{{ $item['id'] }}')"
                    >
                        {{ $item['label'] }}
                    </button>
                @endforeach
            </div>

            <div class="flex flex-wrap gap-2 pt-1">
                <x-filament::button
                    tag="a"
                    :href="$emailHelpUrl"
                    icon="heroicon-o-envelope"
                    color="gray"
                >
                    Open Email System Help (Quick Start)
                </x-filament::button>
                <x-filament::button
                    tag="a"
                    :href="$homeSectionsHelpUrl"
                    icon="heroicon-o-book-open"
                    color="gray"
                >
                    Open Home Sections Documentation
                </x-filament::button>
                <x-filament::button
                    tag="a"
                    :href="$videoHelpUrl"
                    icon="heroicon-o-film"
                    color="gray"
                >
                    Open Video System Help
                </x-filament::button>
            </div>
        </header>

        {{-- Overview cards --}}
        <section id="overview" class="scroll-mt-24 grid gap-4 lg:grid-cols-2">
            <div class="rounded-2xl border border-gray-200/80 bg-white/80 p-5 shadow-sm shadow-gray-100">
                <h2 class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">
                    Purpose
                </h2>
                <p class="mt-1 text-xs text-gray-500">
                    What this document is for.
                </p>

                <ul class="mt-3 list-disc space-y-1.5 pl-4 text-sm text-gray-800">
                    <li>Teach admins/staff how to use the system confidently.</li>
                    <li>Reduce “where do I click?” + “why didn’t it send?” moments.</li>
                    <li>Centralize operational rules: permissions, email sending, donations flow.</li>
                </ul>

                {!! $callout('muted', 'Rule of thumb', 'When something seems “broken”, it’s usually one of: permissions, missing required data, or a queue worker not running.') !!}
            </div>

            <div class="rounded-2xl border border-gray-200/80 bg-white/80 p-5 shadow-sm shadow-gray-100">
                <h2 class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">
                    Mental model
                </h2>
                <p class="mt-1 text-xs text-gray-500">
                    Think in pipelines.
                </p>

                <div class="mt-3 space-y-2 text-sm text-gray-800 leading-relaxed">
                    <div class="rounded-xl border border-sky-200 bg-sky-50 px-3 py-3 text-sm text-sky-900">
                        <div>
                            <span class="font-semibold">Email:</span>
                            Lists → Subscribers → Campaign → Test send → Live send (queued) → Deliveries → Sent/Failed.
                        </div>
                        <div class="mt-2">
                            <span class="font-semibold">Donations:</span>
                            Donation intent → Transaction created → Provider confirms → Receipt + ledger → Refunds/pledges as needed.
                        </div>
                    </div>

                    <p class="text-xs text-gray-500">
                        The admin panel is basically a “control room” for these pipelines.
                    </p>
                </div>
            </div>
        </section>

        {{-- Dashboard --}}
        <section id="dashboard" class="scroll-mt-24 rounded-2xl border border-gray-200/80 bg-white p-5 shadow-sm shadow-gray-100 space-y-3">
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-sm font-semibold tracking-tight text-gray-900">Dashboard</h2>
                {!! $sectionBadge('Admin') !!}
            </div>

            <p class="text-sm text-gray-700">
                Your “home base.” Use it for quick visibility and shortcuts. If a workflow feels “buried,” consider
                adding a dashboard widget for it.
            </p>

            <ul class="list-disc space-y-1.5 pl-4 text-sm text-gray-800">
                <li>Use the sidebar groups to find system areas (Email, Donations, Content, System).</li>
                <li>If things look “stuck,” clear caches (<code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs">php artisan optimize:clear</code>).</li>
            </ul>
        </section>

        {{-- Users / Roles / Permissions --}}
        <section id="users-roles-perms" class="scroll-mt-24 rounded-2xl border border-gray-200/80 bg-white p-5 shadow-sm shadow-gray-100 space-y-3">
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-sm font-semibold tracking-tight text-gray-900">Users / Roles / Permissions</h2>
                {!! $sectionBadge('Access control') !!}
            </div>

            <p class="text-sm text-gray-700">
                Access is controlled using <span class="font-medium">Spatie Roles &amp; Permissions</span>.
                Permissions are specific abilities (e.g. <code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs">users.read</code>),
                roles are bundles (Admin, Director, Editor).
            </p>

            <ul class="list-disc space-y-1.5 pl-4 text-sm text-gray-800">
                <li><span class="font-semibold">Roles</span> should be human-friendly job titles.</li>
                <li><span class="font-semibold">Permissions</span> should be consistent and predictable: <code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs">resource.action</code>.</li>
                <li>Use a dedicated permission for docs pages: <code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs">admin.documentation.view</code>.</li>
            </ul>

            {!! $callout('warn', 'Common “I can’t see it” fix', 'Confirm the user has the right role/permission and is logging into the correct panel/path (admin vs org).') !!}
        </section>

        {{-- Teams / Tenancy --}}
        <section id="teams" class="scroll-mt-24 rounded-2xl border border-gray-200/80 bg-white p-5 shadow-sm shadow-gray-100 space-y-3">
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-sm font-semibold tracking-tight text-gray-900">Teams / Tenancy</h2>
                {!! $sectionBadge('Org / Multi-tenant') !!}
            </div>

            <p class="text-sm text-gray-700">
                If your app uses teams/tenancy, data visibility and permissions can change depending on the active team context.
                When troubleshooting “missing records,” always confirm the active team.
            </p>

            {!! $callout('muted', 'Tip', 'If you ever suspect team scoping is “stuck,” log out/in and re-select the team. Also clear caches in dev.') !!}
        </section>

        {{-- Addresses --}}
        <section id="addresses" class="scroll-mt-24 rounded-2xl border border-gray-200/80 bg-white p-5 shadow-sm shadow-gray-100 space-y-3">
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-sm font-semibold tracking-tight text-gray-900">Addresses</h2>
                {!! $sectionBadge('Physical addresses') !!}
            </div>

            <p class="text-sm text-gray-700">
                Addresses are <span class="font-medium">physical mailing addresses</span> tied to a user.
                They are used for shipping, donor receipts, internal contact records, and general CRM-style data.
            </p>

            <ul class="list-disc space-y-1.5 pl-4 text-sm text-gray-800">
                <li><span class="font-semibold">Primary address</span> should be the main point of contact.</li>
                <li><span class="font-semibold">Display format</span> uses the model accessor (line1/line2/city/state/postal/country).</li>
            </ul>

            {!! $callout('info', 'Data quality rule', 'Prefer consistent country/state formatting (e.g. US states as 2-letter codes) so reporting stays clean.') !!}
        </section>

        {{-- Pages & Translations --}}
        <section id="pages" class="scroll-mt-24 rounded-2xl border border-gray-200/80 bg-white p-5 shadow-sm shadow-gray-100 space-y-3">
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-sm font-semibold tracking-tight text-gray-900">Pages &amp; Translations</h2>
                {!! $sectionBadge('Content') !!}
            </div>

            <p class="text-sm text-gray-700">
                Pages are site content records. Page translations provide locale-specific content variants.
                If content “doesn’t show,” check publish flags, slugs, and locale selection.
            </p>

            <ul class="list-disc space-y-1.5 pl-4 text-sm text-gray-800">
                <li>Prefer stable slugs; change labels/titles instead.</li>
                <li>Translations should be complete for the target locale before switching live traffic.</li>
            </ul>
        </section>

        {{-- Homepage CMS --}}
        <section id="homepage-cms" class="scroll-mt-24 rounded-2xl border border-gray-200/80 bg-white p-5 shadow-sm shadow-gray-100 space-y-4">
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-sm font-semibold tracking-tight text-gray-900">Homepage CMS</h2>
                {!! $sectionBadge('Content + SEO') !!}
            </div>

            <p class="text-sm text-gray-700">
                The homepage now loads core content from the database. Admin updates happen in Filament without code deploys.
                The most important resources are <span class="font-semibold">Home Page Content</span>, <span class="font-semibold">Home Sections</span>, and <span class="font-semibold">FAQ Items</span>.
            </p>

            <div class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
                Need field-level guidance? Use the dedicated
                <a href="{{ $homeSectionsHelpUrl }}" class="font-semibold underline underline-offset-2">
                    Home Sections Documentation
                </a>
                page for section key definitions, item key mapping, and workflow checklists.
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3">
                    <div class="font-semibold text-gray-900">What is DB-driven now</div>
                    <ul class="mt-2 list-disc space-y-1.5 pl-4 text-sm text-gray-800">
                        <li>SEO title + SEO description</li>
                        <li>Section copy + CTA labels/links from Home Sections</li>
                        <li>Hero intro + meeting schedule/location fallback fields</li>
                        <li>FAQ question/answer grid</li>
                        <li>Homepage OG image (when configured)</li>
                    </ul>
                </div>

                <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3">
                    <div class="font-semibold text-gray-900">Safe edit sequence</div>
                    <ol class="mt-2 list-decimal space-y-1.5 pl-5 text-sm text-gray-800">
                        <li>Edit <span class="font-semibold">Home Sections</span> for target language sections like <code class="rounded bg-white px-1.5 py-0.5 text-xs">pre_give_cta</code> and <code class="rounded bg-white px-1.5 py-0.5 text-xs">final_cta</code>.</li>
                        <li>Edit <span class="font-semibold">Home Page Content</span> for SEO/fallback fields.</li>
                        <li>Edit <span class="font-semibold">FAQ Items</span> for context <code class="rounded bg-white px-1.5 py-0.5 text-xs">home</code>.</li>
                        <li>Confirm <code class="rounded bg-white px-1.5 py-0.5 text-xs">is_active</code> is enabled.</li>
                        <li>Load homepage and verify copy + meta tags.</li>
                    </ol>
                </div>
            </div>

            {!! $callout('info', 'Language fallback', 'If no active row exists for the current language, the app falls back to default language content. Keep English complete even if you manage other locales.') !!}
            {!! $callout('muted', 'CTA section ownership', 'Use <code class="rounded bg-white px-1.5 py-0.5 text-[0.7rem]">pre_give_cta</code> for the mid-page bridge above Give, and <code class="rounded bg-white px-1.5 py-0.5 text-[0.7rem]">final_cta</code> for the bottom CTA component near the footer.') !!}
        </section>

        {{-- Media Library --}}
        <section id="media-library" class="scroll-mt-24 rounded-2xl border border-gray-200/80 bg-white p-5 shadow-sm shadow-gray-100 space-y-4">
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-sm font-semibold tracking-tight text-gray-900">Media Library</h2>
                {!! $sectionBadge('Images + Videos') !!}
            </div>

            <p class="text-sm text-gray-700">
                Media is managed through image and video resources. You can upload assets, assign polymorphic relationships, and control page-level fallbacks by role.
            </p>

            <div class="grid gap-4 lg:grid-cols-2">
                <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3">
                    <div class="font-semibold text-gray-900">Resources</div>
                    <ul class="mt-2 list-disc space-y-1.5 pl-4 text-sm text-gray-800">
                        <li><span class="font-semibold">Images</span>: canonical media rows + upload + copy URL.</li>
                        <li><span class="font-semibold">Videos</span>: upload/embed source + metadata for page/home content video playback.</li>
                        <li><span class="font-semibold">Image Groups</span>: reusable sets (gallery/carousel).</li>
                        <li><span class="font-semibold">Image Group Items</span>: managed inside Image Groups via the relation manager.</li>
                        <li><span class="font-semibold">Image Types</span>: classify what the image is (logo, featured, gallery, etc).</li>
                        <li><span class="font-semibold">Image Relationships</span>: polymorphic role assignment (header/featured/og/thumbnail).</li>
                        <li><span class="font-semibold">Video Relationships</span>: polymorphic role assignment (hero/featured/inline video).</li>
                        <li><span class="font-semibold">Image Group Relationships</span>: polymorphic group assignment (gallery/carousel).</li>
                    </ul>
                </div>

                <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3">
                    <div class="font-semibold text-gray-900">Role behavior</div>
                    <ul class="mt-2 list-disc space-y-1.5 pl-4 text-sm text-gray-800">
                        <li><code class="rounded bg-white px-1.5 py-0.5 text-xs">header</code> falls back to <code class="rounded bg-white px-1.5 py-0.5 text-xs">featured</code>.</li>
                        <li><code class="rounded bg-white px-1.5 py-0.5 text-xs">og</code> falls back to <code class="rounded bg-white px-1.5 py-0.5 text-xs">featured</code>.</li>
                        <li>If no assignment resolves, the app checks <span class="font-semibold">Site Media Defaults</span>.</li>
                        <li>If still missing, the view may hide that image block or use hard fallback asset.</li>
                    </ul>
                </div>
            </div>

            <div class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
                <div class="font-semibold">Header background video setup (Pages)</div>
                <ol class="mt-2 list-decimal space-y-1.5 pl-5">
                    <li>Create/select a <span class="font-semibold">Video</span>.</li>
                    <li>Create a <span class="font-semibold">Video Relationship</span>.</li>
                    <li>Set <span class="font-semibold">Related Type</span> to <span class="font-semibold">Page Translation</span>.</li>
                    <li>Set role to <span class="font-semibold">Hero Video</span> (Featured Video is fallback).</li>
                    <li>Ensure both the video and relationship are active.</li>
                    <li>Ensure the target page translation hero mode is set to video.</li>
                </ol>
                <div class="mt-2">
                    Need full step-by-step details? Open
                    <a href="{{ $videoHelpUrl }}" class="font-semibold underline underline-offset-2">
                        Video System Help
                    </a>.
                </div>
            </div>

            {!! $callout('info', 'Attachable type safety', 'Relationship target types are controlled by a system enum allowlist. Unsupported model types are not selectable in admin forms.') !!}
            {!! $callout('muted', 'Sort behavior', 'When you insert a group item at an occupied sort position, existing items shift down automatically to keep order consistent.') !!}
            {!! $callout('warn', 'Keep defaults set', 'Always keep global default roles populated. Missing defaults increase the chance of blank image slots on new pages/locales.') !!}
        </section>

        {{-- SEO Management --}}
        <section id="seo-management" class="scroll-mt-24 rounded-2xl border border-gray-200/80 bg-white p-5 shadow-sm shadow-gray-100 space-y-4">
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-sm font-semibold tracking-tight text-gray-900">SEO Management</h2>
                {!! $sectionBadge('Meta / OG / JSON-LD') !!}
            </div>

            <p class="text-sm text-gray-700">
                Homepage SEO is now controlled by a mix of DB content and layout defaults. Update title/description in Home Page Content,
                and OG image via image assignment/defaults.
            </p>

            <ul class="list-disc space-y-1.5 pl-4 text-sm text-gray-800">
                <li><span class="font-semibold">Meta title</span>: from Home Page Content SEO title.</li>
                <li><span class="font-semibold">Meta description</span>: from Home Page Content SEO description.</li>
                <li><span class="font-semibold">OpenGraph/Twitter image</span>: resolved from homepage OG image then fallbacks.</li>
                <li><span class="font-semibold">FAQ JSON-LD</span>: generated from active home FAQ rows.</li>
            </ul>

            {!! $callout('info', 'Social cache', 'Facebook/LinkedIn/X can cache old OG images. After updates, use their debugging tools to force a refresh.') !!}
        </section>

        {{-- Seed Data / Recovery --}}
        <section id="seed-data" class="scroll-mt-24 rounded-2xl border border-gray-200/80 bg-white p-5 shadow-sm shadow-gray-100 space-y-4">
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-sm font-semibold tracking-tight text-gray-900">Seed Data / Recovery</h2>
                {!! $sectionBadge('Operations') !!}
            </div>

            <p class="text-sm text-gray-700">
                Baseline homepage CMS data is seeded using idempotent seeders. Re-running these seeders is safe and intended for recovery.
            </p>

            <div class="rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-xs text-gray-800">
                <div class="font-semibold">Recommended recovery commands</div>
                <div class="mt-2 space-y-2">
                    <code class="block whitespace-pre-wrap">php artisan db:seed --class=ImageSeeder</code>
                    <code class="block whitespace-pre-wrap">php artisan db:seed --class=SiteMediaDefaultSeeder</code>
                    <code class="block whitespace-pre-wrap">php artisan db:seed --class=HomePageContentSeeder</code>
                    <code class="block whitespace-pre-wrap">php artisan db:seed --class=HomeSectionSeeder</code>
                    <code class="block whitespace-pre-wrap">php artisan db:seed --class=FaqItemSeeder</code>
                </div>
            </div>

            {!! $callout('muted', 'Idempotent behavior', 'These seeders use updateOrCreate keys, so running them multiple times updates baseline rows instead of duplicating them.') !!}
        </section>

        {{-- CMS Troubleshooting --}}
        <section id="cms-troubleshooting" class="scroll-mt-24 rounded-2xl border border-dashed border-gray-300 bg-gray-50/90 p-5 space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-900">CMS Troubleshooting</h2>
                <span class="text-xs text-gray-500">Home content / media / SEO checks</span>
            </div>

            <div class="grid gap-3 lg:grid-cols-2">
                <div class="rounded-xl border border-gray-200 bg-white px-4 py-3">
                    <div class="font-semibold text-gray-900">“Homepage still shows old copy.”</div>
                    <ul class="mt-2 list-disc space-y-1.5 pl-4 text-sm text-gray-800">
                        <li>Confirm the correct language row exists in Home Page Content.</li>
                        <li>Confirm the matching Home Sections row exists for the section key you are editing (for example <code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs">hero</code>, <code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs">pre_give_cta</code>, <code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs">final_cta</code>).</li>
                        <li>Confirm both rows are active (<code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs">is_active = true</code>).</li>
                        <li>Clear cache in non-local environments if needed.</li>
                    </ul>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white px-4 py-3">
                    <div class="font-semibold text-gray-900">“FAQ section disappeared.”</div>
                    <ul class="mt-2 list-disc space-y-1.5 pl-4 text-sm text-gray-800">
                        <li>Check FAQ items for context <code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs">home</code>.</li>
                        <li>Ensure at least one active item for current/default language.</li>
                        <li>Verify sort order and active flags.</li>
                    </ul>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white px-4 py-3">
                    <div class="font-semibold text-gray-900">“OG image is wrong.”</div>
                    <ul class="mt-2 list-disc space-y-1.5 pl-4 text-sm text-gray-800">
                        <li>Check Home Page Content OG image assignment.</li>
                        <li>Check Site Media Defaults for fallback roles.</li>
                        <li>Use social debugger tools to refresh external cache.</li>
                    </ul>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white px-4 py-3">
                    <div class="font-semibold text-gray-900">“Image slot is empty.”</div>
                    <ul class="mt-2 list-disc space-y-1.5 pl-4 text-sm text-gray-800">
                        <li>Verify image row is active and URL/path is valid.</li>
                        <li>Verify an Image Relationship exists for the current content target/language.</li>
                        <li>Verify defaults are seeded.</li>
                    </ul>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white px-4 py-3">
                    <div class="font-semibold text-gray-900">“Image order changed after insert.”</div>
                    <ul class="mt-2 list-disc space-y-1.5 pl-4 text-sm text-gray-800">
                        <li>Group item sort order uses insertion behavior (existing items shift down).</li>
                        <li>Use the Image Group relation manager reorder controls for final ordering.</li>
                        <li>Refresh after save to confirm the updated sort sequence.</li>
                    </ul>
                </div>
            </div>
        </section>

        {{-- Email System --}}
        <section id="email-system" class="scroll-mt-24 rounded-2xl border border-gray-200/80 bg-white p-5 shadow-sm shadow-gray-100 space-y-4">
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-sm font-semibold tracking-tight text-gray-900">Email System</h2>
                {!! $sectionBadge('Marketing email') !!}
            </div>

            <p class="text-sm text-gray-700">
                The email system is designed for <span class="font-medium">marketing lists</span> with opt-in/out control,
                unsubscribe links, and safe queued sending.
            </p>

            <div class="grid gap-4 lg:grid-cols-2">
                <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3">
                    <h3 class="text-xs font-semibold uppercase tracking-[0.14em] text-gray-600">Core objects</h3>
                    <ul class="mt-2 list-disc space-y-1.5 pl-4 text-sm text-gray-800">
                        <li><span class="font-semibold">Email List</span>: a segment (key, purpose=marketing).</li>
                        <li><span class="font-semibold">Subscriber</span>: a person + preferences + tokens.</li>
                        <li><span class="font-semibold">Campaign</span>: subject + body + list association.</li>
                        <li><span class="font-semibold">Delivery</span>: per-subscriber send record + snapshot.</li>
                    </ul>
                </div>

                <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3">
                    <h3 class="text-xs font-semibold uppercase tracking-[0.14em] text-gray-600">Key safety rules</h3>
                    <ul class="mt-2 list-disc space-y-1.5 pl-4 text-sm text-gray-800">
                        <li>Only <span class="font-semibold">marketing</span> lists go through the live pipeline.</li>
                        <li>Always do a <span class="font-semibold">test send</span> before a live send.</li>
                        <li>Live sends require a <span class="font-semibold">queue worker</span>.</li>
                        <li>Subscribers can be skipped if globally unsubscribed or list-unsubscribed.</li>
                    </ul>
                </div>
            </div>

            {!! $callout('info', 'Quick link', 'For a step-by-step walkthrough, use the “Email System Help” page.') !!}
        </section>

        {{-- Email Workflows --}}
        <section id="email-workflows" class="scroll-mt-24 rounded-2xl border border-gray-200/80 bg-white p-5 shadow-sm shadow-gray-100 space-y-4">
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-sm font-semibold tracking-tight text-gray-900">Email Workflows</h2>
                {!! $sectionBadge('How to send') !!}
            </div>

            <div class="space-y-3 text-sm text-gray-700">
                <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3">
                    <div class="font-semibold text-gray-900">Recommended daily workflow</div>
                    <ol class="mt-2 list-decimal space-y-1.5 pl-5 text-sm text-gray-800">
                        <li>Create or select an <span class="font-semibold">Email List</span> (purpose: marketing).</li>
                        <li>Add/verify <span class="font-semibold">Subscribers</span> (opt-in state matters).</li>
                        <li>Create a <span class="font-semibold">Campaign</span> with subject + body.</li>
                        <li>Send a <span class="font-semibold">test email</span> to yourself.</li>
                        <li>Click <span class="font-semibold">Send (LIVE)</span> only after review.</li>
                        <li>Ensure the <span class="font-semibold">queue worker</span> is running until it completes.</li>
                    </ol>
                </div>

                {!! $callout(
                    'warn',
                    'Before you click “Send (LIVE)”',
                    'Confirm: subject is correct, body renders correctly in Gmail, links work, unsubscribe links present, and the list is the correct audience. Live sends are “easy to start” and “annoying to undo.”'
                ) !!}
            </div>
        </section>

        {{-- Email Queue Pipeline --}}
        <section id="email-queue" class="scroll-mt-24 rounded-2xl border border-gray-200/80 bg-white p-5 shadow-sm shadow-gray-100 space-y-4">
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-sm font-semibold tracking-tight text-gray-900">Email Queue Pipeline</h2>
                {!! $sectionBadge('Queue') !!}
            </div>

            <p class="text-sm text-gray-700">
                Live sends are processed through queued jobs. The pipeline is designed to be resilient:
                it can compile missing HTML (GrapesJS), skip unsubscribed users, and record a snapshot for each delivery.
            </p>

            <div class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
                <div class="font-semibold">Pipeline overview</div>
                <ul class="mt-2 list-disc space-y-1.5 pl-4">
                    <li><span class="font-semibold">QueueEmailCampaignSend</span>: validates sendability, enforces marketing-only, chunks subscribers, creates deliveries, dispatches chunk jobs.</li>
                    <li><span class="font-semibold">SendEmailCampaignChunk</span>: loads deliveries, sends each email, stores rendered HTML snapshot, updates counters, finalizes campaign status.</li>
                </ul>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3">
                    <div class="font-semibold text-gray-900 text-sm">Why chunks exist</div>
                    <p class="mt-1 text-sm text-gray-700">
                        Chunks prevent timeouts and keep sending predictable.
                        If a chunk fails, it doesn’t automatically corrupt the whole campaign—deliveries record status.
                    </p>
                </div>

                <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3">
                    <div class="font-semibold text-gray-900 text-sm">Snapshots (Delivery body_html)</div>
                    <p class="mt-1 text-sm text-gray-700">
                        Each delivery stores the rendered HTML so admins can view “what actually sent,” even if templates change later.
                    </p>
                </div>
            </div>

            {!! $callout('muted', 'Local dev shortcut', 'If you want live sends to process immediately without a worker (dev only), set <code class="rounded bg-white px-1.5 py-0.5 text-[0.7rem]">QUEUE_CONNECTION=sync</code>. Do not leave that in production.') !!}
        </section>

        {{-- Email Troubleshooting --}}
        <section id="email-troubleshooting" class="scroll-mt-24 rounded-2xl border border-dashed border-gray-300 bg-gray-50/90 p-5 space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-900">
                    Email Troubleshooting
                </h2>
                <span class="text-xs text-gray-500">Most issues are queue or unsubscribes</span>
            </div>

            <div class="grid gap-3 lg:grid-cols-2">
                <div class="rounded-xl border border-gray-200 bg-white px-4 py-3">
                    <div class="font-semibold text-gray-900">“I clicked Send and nothing happened.”</div>
                    <ul class="mt-2 list-disc space-y-1.5 pl-4 text-sm text-gray-800">
                        <li>Check campaign status: is it <span class="font-semibold">SENDING</span>?</li>
                        <li>Check if a <span class="font-semibold">queue worker</span> is running.</li>
                        <li>In production, confirm supervisor/Horizon/service is active.</li>
                    </ul>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white px-4 py-3">
                    <div class="font-semibold text-gray-900">“Some people didn’t receive it.”</div>
                    <ul class="mt-2 list-disc space-y-1.5 pl-4 text-sm text-gray-800">
                        <li>Check subscriber: globally unsubscribed?</li>
                        <li>Check list pivot: unsubscribed from that list?</li>
                        <li>Check deliveries for SKIPPED/FAILED and the last_error.</li>
                    </ul>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white px-4 py-3">
                    <div class="font-semibold text-gray-900">“GrapesJS campaign sent blank email.”</div>
                    <ul class="mt-2 list-disc space-y-1.5 pl-4 text-sm text-gray-800">
                        <li>Ensure campaign has compiled <span class="font-semibold">body_html</span>.</li>
                        <li>Use test send to confirm rendering before live.</li>
                    </ul>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white px-4 py-3">
                    <div class="font-semibold text-gray-900">“Emails go to spam.”</div>
                    <ul class="mt-2 list-disc space-y-1.5 pl-4 text-sm text-gray-800">
                        <li>Check DNS: SPF, DKIM, DMARC for your sending domain.</li>
                        <li>Avoid spammy subjects and huge image-only emails.</li>
                        <li>Warm up new domains slowly; don’t blast day one.</li>
                    </ul>
                </div>
            </div>

            {!! $callout('danger', 'Production caution', 'If you suspect a large unintended live send: stop the worker first, then investigate campaign status + queued jobs.') !!}
        </section>

        {{-- Donations --}}
        <section id="donations" class="scroll-mt-24 rounded-2xl border border-gray-200/80 bg-white p-5 shadow-sm shadow-gray-100 space-y-4">
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-sm font-semibold tracking-tight text-gray-900">Donations</h2>
                {!! $sectionBadge('Payments') !!}
            </div>

            <p class="text-sm text-gray-700">
                Donations are tracked as financial records. The admin panel exists to help you:
                review activity, reconcile issues, manage refunds, and handle recurring pledges (if enabled).
            </p>

            <div class="grid gap-4 lg:grid-cols-2">
                <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3">
                    <div class="font-semibold text-gray-900">Concepts</div>
                    <ul class="mt-2 list-disc space-y-1.5 pl-4 text-sm text-gray-800">
                        <li><span class="font-semibold">Transaction</span>: a completed (or attempted) payment record.</li>
                        <li><span class="font-semibold">Pledge</span>: a recurring schedule / plan (monthly, etc.).</li>
                        <li><span class="font-semibold">Refund</span>: reversing a payment (full/partial).</li>
                    </ul>
                </div>

                <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3">
                    <div class="font-semibold text-gray-900">Operational rule</div>
                    <p class="mt-2 text-sm text-gray-700">
                        Treat donations data like accounting: edit carefully, prefer adding notes/audit fields
                        instead of deleting records.
                    </p>
                    {!! $callout('warn', 'Never “fix” money by deleting it', 'If a record is wrong, mark it failed/void/refunded with traceable metadata. Deleting financial history makes reconciliation a nightmare.') !!}
                </div>
            </div>
        </section>

        {{-- Transactions --}}
        <section id="transactions" class="scroll-mt-24 rounded-2xl border border-gray-200/80 bg-white p-5 shadow-sm shadow-gray-100 space-y-4">
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-sm font-semibold tracking-tight text-gray-900">Transactions</h2>
                {!! $sectionBadge('Donations') !!}
            </div>

            <p class="text-sm text-gray-700">
                Transactions represent payment attempts and their final state (succeeded/failed/refunded).
                Use transactions for reporting, donor support, and reconciliation.
            </p>

            <div class="grid gap-4 lg:grid-cols-2">
                <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3">
                    <div class="font-semibold text-gray-900">What admins do here</div>
                    <ul class="mt-2 list-disc space-y-1.5 pl-4 text-sm text-gray-800">
                        <li>Search by donor email/name and find the receipt record.</li>
                        <li>Confirm status (paid, failed, refunded).</li>
                        <li>Check processor IDs for support tickets (Stripe charge/payment intent, etc.).</li>
                    </ul>
                </div>

                <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3">
                    <div class="font-semibold text-gray-900">Recommended fields to verify</div>
                    <ul class="mt-2 list-disc space-y-1.5 pl-4 text-sm text-gray-800">
                        <li>Amount and currency</li>
                        <li>Status timestamps (created/paid/refunded/failed)</li>
                        <li>Processor reference IDs</li>
                        <li>Donor contact details</li>
                    </ul>
                </div>
            </div>
        </section>

        {{-- Pledges --}}
        <section id="pledges" class="scroll-mt-24 rounded-2xl border border-gray-200/80 bg-white p-5 shadow-sm shadow-gray-100 space-y-4">
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-sm font-semibold tracking-tight text-gray-900">Pledges (Recurring)</h2>
                {!! $sectionBadge('Recurring') !!}
            </div>

            <p class="text-sm text-gray-700">
                Pledges represent recurring giving schedules (e.g. monthly). They can be paused/disabled automatically after failures.
            </p>

            {!! $callout('info', 'Best practice', 'When recurring issues happen, check: payment method validity, contact details, provider errors, and whether auto-disable thresholds were hit.') !!}

            <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3">
                <div class="font-semibold text-gray-900">Common pledge lifecycle</div>
                <ol class="mt-2 list-decimal space-y-1.5 pl-5 text-sm text-gray-800">
                    <li>Pledge created (amount + frequency + donor).</li>
                    <li>Scheduler runs and attempts charge.</li>
                    <li>Transaction created for each attempt.</li>
                    <li>Failures increment decline count; after threshold pledge may auto-disable.</li>
                </ol>
            </div>
        </section>

        {{-- Refunds --}}
        <section id="refunds" class="scroll-mt-24 rounded-2xl border border-gray-200/80 bg-white p-5 shadow-sm shadow-gray-100 space-y-4">
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-sm font-semibold tracking-tight text-gray-900">Refunds</h2>
                {!! $sectionBadge('Reversals') !!}
            </div>

            <p class="text-sm text-gray-700">
                Refunds reverse a completed transaction. Depending on your payment provider, refunds may take time to settle.
                Always keep a clear internal note for why a refund happened.
            </p>

            <div class="grid gap-4 lg:grid-cols-2">
                <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3">
                    <div class="font-semibold text-gray-900">Refund checklist</div>
                    <ul class="mt-2 list-disc space-y-1.5 pl-4 text-sm text-gray-800">
                        <li>Confirm you selected the correct transaction.</li>
                        <li>Confirm whether refund is full or partial.</li>
                        <li>Add a reason note for bookkeeping/support.</li>
                        <li>Verify provider status updates (webhooks).</li>
                    </ul>
                </div>

                <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3">
                    <div class="font-semibold text-gray-900">Caution</div>
                    <p class="mt-2 text-sm text-gray-700">
                        Refunds affect reporting. Avoid “manual edits” to amounts—prefer an explicit refund record tied to the original transaction.
                    </p>
                </div>
            </div>
        </section>

        {{-- Donations Troubleshooting --}}
        <section id="donations-troubleshooting" class="scroll-mt-24 rounded-2xl border border-dashed border-gray-300 bg-gray-50/90 p-5 space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-900">
                    Donations Troubleshooting
                </h2>
                <span class="text-xs text-gray-500">Most issues are provider/webhook/metadata</span>
            </div>

            <div class="grid gap-3 lg:grid-cols-2">
                <div class="rounded-xl border border-gray-200 bg-white px-4 py-3">
                    <div class="font-semibold text-gray-900">“Donor says they paid but I can’t find it.”</div>
                    <ul class="mt-2 list-disc space-y-1.5 pl-4 text-sm text-gray-800">
                        <li>Search by email/name and check recent transactions.</li>
                        <li>Confirm you’re looking in the correct environment (test vs live).</li>
                        <li>If you have provider IDs, search by those.</li>
                    </ul>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white px-4 py-3">
                    <div class="font-semibold text-gray-900">“Refund says failed / not reflected.”</div>
                    <ul class="mt-2 list-disc space-y-1.5 pl-4 text-sm text-gray-800">
                        <li>Check provider dashboard for the refund status.</li>
                        <li>Confirm webhooks are configured and arriving.</li>
                        <li>Reconcile the local record vs provider record.</li>
                    </ul>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white px-4 py-3">
                    <div class="font-semibold text-gray-900">“Recurring pledge stopped charging.”</div>
                    <ul class="mt-2 list-disc space-y-1.5 pl-4 text-sm text-gray-800">
                        <li>Check if pledge auto-disabled after decline threshold.</li>
                        <li>Check donor payment method validity/expiry.</li>
                        <li>Review last transaction error message.</li>
                    </ul>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white px-4 py-3">
                    <div class="font-semibold text-gray-900">“Webhook errors / duplicates.”</div>
                    <ul class="mt-2 list-disc space-y-1.5 pl-4 text-sm text-gray-800">
                        <li>Confirm webhook signing secret and endpoint URL.</li>
                        <li>Ensure idempotency handling is correct (don’t double-create transactions).</li>
                    </ul>
                </div>
            </div>

            {!! $callout('warn', 'When in doubt', 'Always compare your local record to the provider dashboard. The provider is the source of truth for money movement.') !!}
        </section>

        {{-- Operations & Playbooks --}}
        <section id="ops" class="scroll-mt-24 rounded-2xl border border-gray-200/80 bg-white p-5 shadow-sm shadow-gray-100 space-y-4">
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-sm font-semibold tracking-tight text-gray-900">Operations &amp; Playbooks</h2>
                {!! $sectionBadge('Support') !!}
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3">
                    <div class="font-semibold text-gray-900">“User can’t access admin.”</div>
                    <ul class="mt-2 list-disc space-y-1.5 pl-4 text-sm text-gray-800">
                        <li>Confirm they’re logging into <span class="font-semibold">/admin</span> (not org panel).</li>
                        <li>Confirm they have <code class="rounded bg-white px-1.5 py-0.5 text-[0.7rem]">admin.panel</code> permission.</li>
                        <li>Confirm role assignment and cached permissions are fresh.</li>
                    </ul>
                </div>

                <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3">
                    <div class="font-semibold text-gray-900">“Campaign is stuck in SENDING.”</div>
                    <ul class="mt-2 list-disc space-y-1.5 pl-4 text-sm text-gray-800">
                        <li>Check queue worker status.</li>
                        <li>Check if there are pending chunks &gt; 0.</li>
                        <li>Check for failed jobs and inspect exception messages.</li>
                    </ul>
                </div>

                <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3">
                    <div class="font-semibold text-gray-900">“Receipt / email missing”</div>
                    <ul class="mt-2 list-disc space-y-1.5 pl-4 text-sm text-gray-800">
                        <li>Confirm mail config for that environment.</li>
                        <li>Confirm the email address on file is correct and not unsubscribed.</li>
                        <li>Check logs for mail send errors.</li>
                    </ul>
                </div>

                <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3">
                    <div class="font-semibold text-gray-900">Admin housekeeping</div>
                    <ul class="mt-2 list-disc space-y-1.5 pl-4 text-sm text-gray-800">
                        <li>Run tests before deploying.</li>
                        <li>Keep queue worker/service healthy.</li>
                        <li>Regularly review permissions and remove unused ones.</li>
                    </ul>
                </div>
            </div>
        </section>

        {{-- Testing & Maintenance --}}
        <section id="testing" class="scroll-mt-24 rounded-2xl border border-gray-200/80 bg-white p-5 shadow-sm shadow-gray-100 space-y-3">
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-sm font-semibold tracking-tight text-gray-900">Testing &amp; Maintenance</h2>
                {!! $sectionBadge('Dev') !!}
            </div>

            <p class="text-sm text-gray-700">
                Keep the system stable by running tests regularly and watching code coverage in critical pipelines
                (email queue jobs, donation processing, webhook handlers).
            </p>

            <div class="rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-xs text-gray-800">
                <div class="font-semibold">Useful commands</div>
                <div class="mt-2 space-y-2">
                    <code class="block whitespace-pre-wrap">php artisan test</code>
                    <code class="block whitespace-pre-wrap">php artisan optimize:clear</code>
                    <code class="block whitespace-pre-wrap">php artisan queue:work</code>
                </div>
            </div>

            {!! $callout('muted', 'Coverage note', 'Don’t chase 100% coverage. Prioritize high-risk areas: money movement, live email sending, permissions gates, and webhook handlers.') !!}
        </section>

        {{-- Footer recap --}}
        <section class="rounded-2xl border border-dashed border-gray-300 bg-gray-50/90 p-4 text-xs text-gray-650">
            <p class="text-[0.8rem] text-gray-700">
                <span class="font-semibold">Quick recap:</span>
                Access control → Content → Email pipeline → Donations pipeline → Support playbooks → Tests keep it sane.
            </p>
        </section>
    </div>
</x-filament-panels::page>
