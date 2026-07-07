<article @if(($page['right_to_left'] ?? false)) dir="rtl" @endif>
    @if (filled($page['custom_html'] ?? null))
        <div class="prose prose-slate max-w-none">
            {!! $page['custom_html'] !!}
        </div>
    @endif
</article>

