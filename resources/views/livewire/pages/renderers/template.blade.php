@includeIf('livewire.pages.templates.' . $template, ['page' => $page])

@if (!view()->exists('livewire.pages.templates.' . $template))
    <article class="prose prose-slate max-w-none">
        <h1>{!! $page['title'] !!}</h1>
        <p>{!! $page['description'] !!}</p>
        <div>{!! $page['content'] !!}</div>
    </article>
@endif

