{{-- resources/views/livewire/pages/show-page.blade.php --}}
<div class="space-y-4">
    @if (session('status'))
        <div class="text-center bg-green-700 text-gray-100 py-2 px-3 rounded">
            {{ session('status') }}
        </div>
    @endif

    @if ($translation)
        <article
            class="prose prose-slate max-w-none"
            @if(optional($translation->language)->right_to_left)
                dir="rtl"
            @endif
        >
            <h1 class="mb-2">{{ $translation->title }}</h1>

            <p class="text-lg text-slate-600">
                {{ $translation->description }}
            </p>

            <div class="mt-4">
                {!! $translation->content !!}
            </div>
        </article>
    @else
        {{-- During Livewire re-resolve / redirect --}}
        <div class="text-center text-slate-500 py-10">
            Loading page…
        </div>
    @endif
</div>
