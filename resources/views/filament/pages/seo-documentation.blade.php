<x-filament-panels::page>
    @php
        $routeSeoUrl = \App\Filament\Resources\RouteSeoResource::getUrl('index');
        $homeContentUrl = \App\Filament\Resources\HomePageContents\HomePageContentResource::getUrl('index');
        $pageTranslationsUrl = \App\Filament\Resources\PageTranslationResource::getUrl('index');
        $adminDocsUrl = \App\Filament\Pages\AdminDocumentation::getUrl();

        $sections = [
            ['id' => 'overview', 'label' => 'Overview'],
            ['id' => 'matrix', 'label' => 'Source of Truth'],
            ['id' => 'route-seo', 'label' => 'Route SEO CMS'],
            ['id' => 'page-translation', 'label' => 'Page Translation SEO'],
            ['id' => 'home-seo', 'label' => 'Homepage SEO'],
            ['id' => 'keyword-pages', 'label' => 'Keyword Pages'],
            ['id' => 'fallbacks', 'label' => 'Fallback Rules'],
            ['id' => 'google', 'label' => 'Google Setup'],
            ['id' => 'noindex', 'label' => 'Noindex Policy'],
            ['id' => 'qa', 'label' => 'QA Checklist'],
            ['id' => 'troubleshooting', 'label' => 'Troubleshooting'],
        ];
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
        <header class="space-y-4 rounded-2xl bg-gradient-to-r from-primary-600/10 via-primary-500/5 to-primary-600/10 p-6 ring-1 ring-primary-500/10">
            <div class="space-y-2">
                <div class="inline-flex items-center gap-2 rounded-full bg-white/70 px-3 py-1 text-xs font-medium text-primary-700 ring-1 ring-primary-100">
                    SEO Guide
                </div>
                <h1 class="text-2xl font-semibold text-gray-900">SEO Documentation</h1>
                <p class="max-w-4xl text-sm text-gray-700">
                    Complete reference for how SEO metadata is managed in this app, which pages are DB-managed,
                    which pages are code-controlled, how fallback resolution works, and how to verify Google setup.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                @foreach ($sections as $item)
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
                <x-filament::button tag="a" :href="$routeSeoUrl" icon="heroicon-o-magnifying-glass-circle" color="gray">Open Route SEO</x-filament::button>
                <x-filament::button tag="a" :href="$homeContentUrl" icon="heroicon-o-home" color="gray">Open Home Page Content</x-filament::button>
                <x-filament::button tag="a" :href="$pageTranslationsUrl" icon="heroicon-o-language" color="gray">Open Page Translations</x-filament::button>
                <x-filament::button tag="a" :href="$adminDocsUrl" icon="heroicon-o-book-open" color="gray">Back to Admin Documentation</x-filament::button>
            </div>
        </header>

        <section id="overview" class="scroll-mt-24 rounded-2xl border border-gray-200 bg-white p-5 space-y-3">
            <h2 class="text-sm font-semibold tracking-tight text-gray-900">SEO System Overview</h2>
            <p class="text-sm text-gray-700">
                The app uses a mixed SEO model by design:
            </p>
            <ul class="list-disc space-y-1.5 pl-4 text-sm text-gray-800">
                <li><span class="font-semibold">DB-managed SEO</span> for indexable marketing pages editors should control.</li>
                <li><span class="font-semibold">Code-controlled SEO</span> for sensitive/system pages that must stay <code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs">noindex,nofollow</code>.</li>
                <li><span class="font-semibold">Global defaults</span> from shared layout SEO config.</li>
            </ul>
        </section>

        <section id="matrix" class="scroll-mt-24 rounded-2xl border border-gray-200 bg-white p-5 space-y-3">
            <h2 class="text-sm font-semibold tracking-tight text-gray-900">Source of Truth Matrix</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm text-gray-800">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-600">
                        <tr>
                            <th class="px-3 py-2">Route</th>
                            <th class="px-3 py-2">Managed In</th>
                            <th class="px-3 py-2">Indexability</th>
                            <th class="px-3 py-2">Notes</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr><td class="px-3 py-2"><code>/give</code></td><td class="px-3 py-2">Route SEO resource (<code>donations.show</code>)</td><td class="px-3 py-2">index,follow</td><td class="px-3 py-2">Stored in unified <code>seo_meta</code> with language fallback.</td></tr>
                        <tr><td class="px-3 py-2"><code>/pages</code></td><td class="px-3 py-2">Route SEO resource (<code>pages.index</code>)</td><td class="px-3 py-2">index,follow</td><td class="px-3 py-2">Stored in unified <code>seo_meta</code> with language fallback.</td></tr>
                        <tr><td class="px-3 py-2"><code>/emails/subscribe</code></td><td class="px-3 py-2">Route SEO resource (<code>emails.subscribe</code>)</td><td class="px-3 py-2">index,follow</td><td class="px-3 py-2">Stored in unified <code>seo_meta</code> with language fallback.</td></tr>
                        <tr><td class="px-3 py-2"><code>/pages/{slug}</code></td><td class="px-3 py-2">Page Translation + SEO relation</td><td class="px-3 py-2">index,follow</td><td class="px-3 py-2">Falls back to translation title/description if SEO row missing.</td></tr>
                        <tr><td class="px-3 py-2"><code>/</code></td><td class="px-3 py-2">Home Page Content + SEO relation</td><td class="px-3 py-2">index,follow</td><td class="px-3 py-2">Falls back to service defaults and includes JSON-LD output.</td></tr>
                        <tr><td class="px-3 py-2"><code>/donations/thank-you*</code></td><td class="px-3 py-2">Code</td><td class="px-3 py-2">noindex,nofollow</td><td class="px-3 py-2">Intentional system-page protection.</td></tr>
                        <tr><td class="px-3 py-2"><code>/unsubscribe/{token}</code>, <code>/email-preferences/{token}</code></td><td class="px-3 py-2">Code</td><td class="px-3 py-2">noindex,nofollow</td><td class="px-3 py-2">Tokenized user-specific routes.</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section id="route-seo" class="scroll-mt-24 rounded-2xl border border-gray-200 bg-white p-5 space-y-3">
            <h2 class="text-sm font-semibold tracking-tight text-gray-900">Route SEO CMS Guide</h2>
            <ol class="list-decimal space-y-1.5 pl-5 text-sm text-gray-800">
                <li>Open <span class="font-semibold">Route SEO</span> resource.</li>
                <li>Select one of the allowed route keys: <code>donations.show</code>, <code>pages.index</code>, <code>emails.subscribe</code>.</li>
                <li>Select language row.</li>
                <li>Set <span class="font-semibold">SEO title</span>, <span class="font-semibold">SEO description</span>, optional <span class="font-semibold">OG image URL</span>.</li>
                <li>Optionally set <span class="font-semibold">canonical path</span> (usually leave to defaults unless intentionally overriding).</li>
                <li>Ensure <code>is_active</code> is enabled.</li>
            </ol>
        </section>

        <section id="page-translation" class="scroll-mt-24 rounded-2xl border border-gray-200 bg-white p-5 space-y-3">
            <h2 class="text-sm font-semibold tracking-tight text-gray-900">Page Translation SEO Guide</h2>
            <ul class="list-disc space-y-1.5 pl-4 text-sm text-gray-800">
                <li>Page translation SEO is resolved from related <code>seo_meta</code> rows for that translation/language.</li>
                <li>If SEO row is missing, the app falls back to translation title/description automatically.</li>
                <li>Canonical URL uses the resolved active translation slug.</li>
            </ul>
        </section>

        <section id="home-seo" class="scroll-mt-24 rounded-2xl border border-gray-200 bg-white p-5 space-y-3">
            <h2 class="text-sm font-semibold tracking-tight text-gray-900">Homepage SEO Guide</h2>
            <ul class="list-disc space-y-1.5 pl-4 text-sm text-gray-800">
                <li>Home SEO is resolved from related <code>seo_meta</code> rows on <span class="font-semibold">Home Page Content</span>.</li>
                <li>If missing, the app falls back to home defaults and global SEO config.</li>
                <li>Homepage OG image uses content assignment with fallback.</li>
                <li>Organization/WebSite/FAQ JSON-LD is output from homepage rendering pipeline.</li>
            </ul>
        </section>

        <section id="keyword-pages" class="scroll-mt-24 rounded-2xl border border-gray-200 bg-white p-5 space-y-3">
            <h2 class="text-sm font-semibold tracking-tight text-gray-900">Keyword Page Playbook</h2>
            <p class="text-sm text-gray-700">
                Dedicated SEO keyword pages are managed in <span class="font-semibold">Page Translations</span>.
                The seeded baseline page is <code>/pages/homeless-ministry-sacramento</code>, owned by
                <code>Database\Seeders\HomelessMinistrySacramentoPageSeeder</code>.
            </p>
            <ol class="list-decimal space-y-1.5 pl-5 text-sm text-gray-800">
                <li>Keep slug stable once indexed unless a redirect strategy exists.</li>
                <li>Use one primary keyword in <code>seo_title</code>, plus natural variants in body copy.</li>
                <li>Keep <code>seo_description</code> concise (about 140-160 chars) with local intent.</li>
                <li>Set a clear conversion CTA (for this page: donation CTA to <code>/give</code>).</li>
                <li>Add at least two internal links from authoritative pages (home and give are already wired).</li>
                <li>To restore this page only, run <code>php artisan db:seed --class=HomelessMinistrySacramentoPageSeeder</code>.</li>
            </ol>
        </section>

        <section id="fallbacks" class="scroll-mt-24 rounded-2xl border border-gray-200 bg-white p-5 space-y-3">
            <h2 class="text-sm font-semibold tracking-tight text-gray-900">Fallback and Resolution Rules</h2>
            <p class="text-sm text-gray-700">SEO resolves in this order:</p>
            <ol class="list-decimal space-y-1.5 pl-5 text-sm text-gray-800">
                <li>Active <code>seo_meta</code> row for current language.</li>
                <li>Active <code>seo_meta</code> row for default language.</li>
                <li>Model content fallback (<code>title</code>/<code>description</code>) where applicable.</li>
                <li>Safe hardcoded/config defaults in resolver.</li>
            </ol>
        </section>

        <section id="google" class="scroll-mt-24 rounded-2xl border border-gray-200 bg-white p-5 space-y-3">
            <h2 class="text-sm font-semibold tracking-tight text-gray-900">Search Console + GA4 Setup</h2>
            <p class="text-sm text-gray-700">Set these environment variables in production:</p>
            <div class="rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-xs text-gray-800">
                <code class="block whitespace-pre-wrap">SEO_GOOGLE_SITE_VERIFICATION=your-google-token</code>
                <code class="block whitespace-pre-wrap">SEO_GA4_MEASUREMENT_ID=G-XXXXXXXXXX</code>
            </div>
            <p class="text-sm text-gray-700">Then run <code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs">php artisan config:clear</code> and verify tags in page source.</p>
        </section>

        <section id="noindex" class="scroll-mt-24 rounded-2xl border border-gray-200 bg-white p-5 space-y-3">
            <h2 class="text-sm font-semibold tracking-tight text-gray-900">Noindex Policy</h2>
            <p class="text-sm text-gray-700">These pages intentionally stay code-controlled and non-editable in Route SEO:</p>
            <ul class="list-disc space-y-1.5 pl-4 text-sm text-gray-800">
                <li><code>/donations/thank-you</code></li>
                <li><code>/donations/thank-you-subscription</code></li>
                <li><code>/unsubscribe/{token}</code></li>
                <li><code>/email-preferences/{token}</code></li>
            </ul>
        </section>

        <section id="qa" class="scroll-mt-24 rounded-2xl border border-gray-200 bg-white p-5 space-y-3">
            <h2 class="text-sm font-semibold tracking-tight text-gray-900">Editor QA Checklist</h2>
            <ul class="list-disc space-y-1.5 pl-4 text-sm text-gray-800">
                <li>Title and description are present and meaningful.</li>
                <li>Correct language row is active.</li>
                <li>Canonical points to intended URL.</li>
                <li>OG image URL is valid and publicly accessible.</li>
                <li>Indexable pages show <code>index,follow</code>.</li>
                <li>System/token pages show <code>noindex,nofollow</code>.</li>
            </ul>
        </section>

        <section id="troubleshooting" class="scroll-mt-24 rounded-2xl border border-gray-200 bg-white p-5 space-y-3">
            <h2 class="text-sm font-semibold tracking-tight text-gray-900">Troubleshooting</h2>
            <ul class="list-disc space-y-1.5 pl-4 text-sm text-gray-800">
                <li><span class="font-semibold">SEO change not visible:</span> check active row + language, then clear config/cache.</li>
                <li><span class="font-semibold">Wrong language meta:</span> verify <code>language_id</code> row exists and is active for route key.</li>
                <li><span class="font-semibold">GSC says excluded:</span> inspect rendered <code>meta robots</code> and canonical tags on that URL.</li>
                <li><span class="font-semibold">Social preview stale:</span> refresh with platform URL debugger tools.</li>
            </ul>
        </section>
    </div>
</x-filament-panels::page>
