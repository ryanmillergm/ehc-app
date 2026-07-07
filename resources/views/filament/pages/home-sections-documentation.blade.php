<x-filament-panels::page>
    @php
        $nav = [
            ['id' => 'overview', 'label' => 'Overview'],
            ['id' => 'section-keys', 'label' => 'Section Keys'],
            ['id' => 'section-fields', 'label' => 'Section Fields'],
            ['id' => 'item-fields', 'label' => 'Item Fields'],
            ['id' => 'item-keys', 'label' => 'Item Keys by Section'],
            ['id' => 'fallbacks', 'label' => 'Fallbacks'],
            ['id' => 'workflow', 'label' => 'Edit Workflow'],
            ['id' => 'troubleshooting', 'label' => 'Troubleshooting'],
            ['id' => 'seed-recovery', 'label' => 'Seed / Recovery'],
            ['id' => 'qa-checklist', 'label' => 'QA Checklist'],
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
                    Home Sections Guide
                </div>
                <h1 class="text-2xl font-semibold text-gray-900">Home Sections Documentation</h1>
                <p class="max-w-4xl text-sm text-gray-700">
                    Detailed admin guide for the homepage section CMS. This page explains what each section key controls,
                    what every field does, how item keys map to UI blocks, and how fallback behavior works.
                </p>
            </div>

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
        </header>

        <section id="overview" class="scroll-mt-24 rounded-2xl border border-gray-200 bg-white p-5 space-y-3">
            <h2 class="text-sm font-semibold tracking-tight text-gray-900">Overview</h2>
            <p class="text-sm text-gray-700">
                Homepage copy is loaded by <code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs">HomeContentService</code>.
                It resolves active rows in <code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs">home_sections</code> by language and section key,
                then merges related <code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs">home_section_items</code>.
            </p>
            <ul class="list-disc space-y-1.5 pl-4 text-sm text-gray-800">
                <li>Use one row per language + section key.</li>
                <li>Set <code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs">is_active</code> to publish a row.</li>
                <li>Use <code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs">sort_order</code> for processing priority.</li>
            </ul>
        </section>

        <section id="section-keys" class="scroll-mt-24 rounded-2xl border border-gray-200 bg-white p-5 space-y-3">
            <h2 class="text-sm font-semibold tracking-tight text-gray-900">Section Keys</h2>
            <p class="text-sm text-gray-700">Current supported keys from <code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs">HomeSectionKey</code>:</p>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm text-gray-800">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-600">
                        <tr>
                            <th class="px-3 py-2">Section Key</th>
                            <th class="px-3 py-2">Label</th>
                            <th class="px-3 py-2">Homepage Area</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr><td class="px-3 py-2"><code>hero</code></td><td class="px-3 py-2">Hero</td><td class="px-3 py-2">Top hero area, intro + CTAs + quick choices.</td></tr>
                        <tr><td class="px-3 py-2"><code>impact_stats</code></td><td class="px-3 py-2">Impact Stats</td><td class="px-3 py-2">Stats band under hero.</td></tr>
                        <tr><td class="px-3 py-2"><code>about</code></td><td class="px-3 py-2">About</td><td class="px-3 py-2">Mission/overview section and bullets.</td></tr>
                        <tr><td class="px-3 py-2"><code>pathway</code></td><td class="px-3 py-2">Pathway</td><td class="px-3 py-2">3-phase process cards.</td></tr>
                        <tr><td class="px-3 py-2"><code>parallax</code></td><td class="px-3 py-2">Parallax</td><td class="px-3 py-2">Parallax bridge banner.</td></tr>
                        <tr><td class="px-3 py-2"><code>serve</code></td><td class="px-3 py-2">Serve</td><td class="px-3 py-2">Volunteer section + CTAs.</td></tr>
                        <tr><td class="px-3 py-2"><code>serve_support</code></td><td class="px-3 py-2">Serve Support</td><td class="px-3 py-2">Support list near Serve section.</td></tr>
                        <tr><td class="px-3 py-2"><code>pre_give_cta</code></td><td class="px-3 py-2">Pre-Give CTA</td><td class="px-3 py-2">Bridge CTA above Give form.</td></tr>
                        <tr><td class="px-3 py-2"><code>give</code></td><td class="px-3 py-2">Give</td><td class="px-3 py-2">Donation narrative + impact cards.</td></tr>
                        <tr><td class="px-3 py-2"><code>visit</code></td><td class="px-3 py-2">Visit</td><td class="px-3 py-2">Meeting schedule/location/map area.</td></tr>
                        <tr><td class="px-3 py-2"><code>final_cta</code></td><td class="px-3 py-2">Final CTA</td><td class="px-3 py-2">Bottom CTA component near footer.</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section id="section-fields" class="scroll-mt-24 rounded-2xl border border-gray-200 bg-white p-5 space-y-3">
            <h2 class="text-sm font-semibold tracking-tight text-gray-900">Home Section Fields</h2>
            <ul class="list-disc space-y-1.5 pl-4 text-sm text-gray-800">
                <li><code>language_id</code>: Locale context for this row.</li>
                <li><code>section_key</code>: Which homepage section this record powers.</li>
                <li><code>eyebrow</code>: Small label line above heading.</li>
                <li><code>heading</code>: Main section title.</li>
                <li><code>subheading</code>: Supporting heading text.</li>
                <li><code>body</code>: Main paragraph content.</li>
                <li><code>note</code>: Supplemental short text used by specific blocks.</li>
                <li><code>cta_primary_label</code>, <code>cta_secondary_label</code>, <code>cta_tertiary_label</code>: Button labels.</li>
                <li><code>cta_primary_url</code>, <code>cta_secondary_url</code>, <code>cta_tertiary_url</code>: Button targets.</li>
                <li><code>image_id</code>: Optional primary image for the section.</li>
                <li><code>meta</code>: JSON key/value for section-specific extras (map URL, scripture ref, etc.).</li>
                <li><code>sort_order</code>: Display/processing order among same language rows.</li>
                <li><code>is_active</code>: Publish toggle.</li>
            </ul>
        </section>

        <section id="item-fields" class="scroll-mt-24 rounded-2xl border border-gray-200 bg-white p-5 space-y-3">
            <h2 class="text-sm font-semibold tracking-tight text-gray-900">Home Section Item Fields</h2>
            <p class="text-sm text-gray-700">
                Items are managed from the <span class="font-semibold">Items</span> relation manager inside a Home Section record.
            </p>
            <ul class="list-disc space-y-1.5 pl-4 text-sm text-gray-800">
                <li><code>item_key</code>: Defines item collection type (example: <code>phase</code> or <code>impact_card</code>).</li>
                <li><code>label</code>: Small label/badge value (often used for step numbers).</li>
                <li><code>title</code>: Main item text.</li>
                <li><code>description</code>: Supporting content.</li>
                <li><code>value</code>: Optional extra value for future templates.</li>
                <li><code>url</code>: Optional link target for clickable items.</li>
                <li><code>sort_order</code>: Item ordering within same section + item key.</li>
                <li><code>is_active</code>: Publish toggle for this item.</li>
            </ul>
        </section>

        <section id="item-keys" class="scroll-mt-24 rounded-2xl border border-gray-200 bg-white p-5 space-y-3">
            <h2 class="text-sm font-semibold tracking-tight text-gray-900">Item Keys by Section</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm text-gray-800">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-600">
                        <tr>
                            <th class="px-3 py-2">Section Key</th>
                            <th class="px-3 py-2">Expected Item Key(s)</th>
                            <th class="px-3 py-2">Purpose</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr><td class="px-3 py-2"><code>hero</code></td><td class="px-3 py-2"><code>quick_choice</code></td><td class="px-3 py-2">Small quick action cards below hero CTA row.</td></tr>
                        <tr><td class="px-3 py-2"><code>impact_stats</code></td><td class="px-3 py-2"><code>stat</code></td><td class="px-3 py-2">Stat cards under hero.</td></tr>
                        <tr><td class="px-3 py-2"><code>about</code></td><td class="px-3 py-2"><code>bullet</code></td><td class="px-3 py-2">Bullet checklist in About box.</td></tr>
                        <tr><td class="px-3 py-2"><code>pathway</code></td><td class="px-3 py-2"><code>phase</code></td><td class="px-3 py-2">Three-step pathway cards.</td></tr>
                        <tr><td class="px-3 py-2"><code>serve_support</code></td><td class="px-3 py-2"><code>easy_yes</code></td><td class="px-3 py-2">Support opportunities list.</td></tr>
                        <tr><td class="px-3 py-2"><code>give</code></td><td class="px-3 py-2"><code>impact_card</code></td><td class="px-3 py-2">Impact cards near donation form.</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section id="fallbacks" class="scroll-mt-24 rounded-2xl border border-gray-200 bg-white p-5 space-y-3">
            <h2 class="text-sm font-semibold tracking-tight text-gray-900">Fallback Behavior</h2>
            <ol class="list-decimal space-y-1.5 pl-5 text-sm text-gray-800">
                <li>App resolves current language section rows where <code>is_active = true</code>.</li>
                <li>If missing, it falls back to default language rows.</li>
                <li>If still missing, hardcoded safe defaults are rendered.</li>
            </ol>
            <p class="text-sm text-gray-700">
                This means homepage will still render even if CMS data is incomplete, but your desired copy/images may not appear.
            </p>
        </section>

        <section id="workflow" class="scroll-mt-24 rounded-2xl border border-gray-200 bg-white p-5 space-y-3">
            <h2 class="text-sm font-semibold tracking-tight text-gray-900">Safe Edit Workflow</h2>
            <ol class="list-decimal space-y-1.5 pl-5 text-sm text-gray-800">
                <li>Open <span class="font-semibold">Home Sections</span> and filter by language.</li>
                <li>Edit base copy fields on section row (heading/body/CTAs/meta/image).</li>
                <li>Edit or reorder related section items in the Items relation manager.</li>
                <li>Confirm <code>is_active</code> is enabled for both section and items.</li>
                <li>Open public homepage and verify content, links, and responsive layout.</li>
                <li>Verify meta title/description/OG image if homepage SEO was also changed.</li>
            </ol>
        </section>

        <section id="troubleshooting" class="scroll-mt-24 rounded-2xl border border-gray-200 bg-white p-5 space-y-3">
            <h2 class="text-sm font-semibold tracking-tight text-gray-900">Troubleshooting</h2>
            <ul class="list-disc space-y-1.5 pl-4 text-sm text-gray-800">
                <li>Changes not visible: verify active language row exists and has <code>is_active</code> enabled.</li>
                <li>Wrong copy still showing: check for older active row with lower <code>sort_order</code>.</li>
                <li>Missing list items: verify matching <code>item_key</code> and active item rows.</li>
                <li>Button goes nowhere: validate URL format (<code>#anchor</code>, absolute URL, or route path).</li>
                <li>Unexpected fallback text: target section key row may be missing or inactive.</li>
            </ul>
        </section>

        <section id="seed-recovery" class="scroll-mt-24 rounded-2xl border border-gray-200 bg-white p-5 space-y-3">
            <h2 class="text-sm font-semibold tracking-tight text-gray-900">Seed / Recovery Commands</h2>
            <p class="text-sm text-gray-700">
                Home CMS baseline can be safely restored with idempotent seeders:
            </p>
            <pre class="overflow-x-auto rounded-xl bg-gray-900 p-4 text-xs text-gray-100"><code>php artisan db:seed --class=HomePageContentSeeder
php artisan db:seed --class=HomeSectionSeeder
php artisan db:seed --class=FaqItemSeeder</code></pre>
            <p class="text-sm text-gray-700">
                Run after migrations on production when deploying CMS schema/content updates.
            </p>
        </section>

        <section id="qa-checklist" class="scroll-mt-24 rounded-2xl border border-gray-200 bg-white p-5 space-y-3">
            <h2 class="text-sm font-semibold tracking-tight text-gray-900">QA Checklist</h2>
            <ul class="list-disc space-y-1.5 pl-4 text-sm text-gray-800">
                <li>Hero copy, CTAs, and quick choices render expected values.</li>
                <li>Pathway phases and Give impact cards show intended order.</li>
                <li>Pre-Give CTA and Final CTA both render correct copy and links.</li>
                <li>Visit schedule/location/map link values match current outreach schedule.</li>
                <li>No placeholder/default text remains where custom content is required.</li>
                <li>Mobile and desktop layouts both look correct.</li>
            </ul>
        </section>
    </div>
</x-filament-panels::page>

