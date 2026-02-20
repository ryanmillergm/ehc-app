<x-layouts.app
    :title="$seo['title']"
    :meta-title="$seo['metaTitle']"
    :meta-description="$seo['metaDescription']"
    :meta-robots="$seo['metaRobots']"
    :canonical-url="$seo['canonicalUrl']"
    :og-type="$seo['ogType']"
    :og-title="$seo['ogTitle']"
    :og-description="$seo['ogDescription']"
    :og-image="$seo['ogImage']"
    :twitter-title="$seo['twitterTitle']"
    :twitter-description="$seo['twitterDescription']"
    :twitter-image="$seo['twitterImage']"
>
    <div class="mx-auto max-w-2xl px-6 py-12">
        <h1 class="text-3xl font-extrabold text-slate-900">Stay connected</h1>
        <p class="mt-3 text-slate-600">Monthly updates: outreach stories, needs, and ways to help.</p>

        <div class="mt-8 rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <livewire:email-signup-form variant="page" />
        </div>
    </div>
</x-layouts.app>
