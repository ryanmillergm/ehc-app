<div>
    @if (session('status'))
        <div class="text-center bg-green-700 text-gray-200">
            {{ session('status') }}
        </div>
    @endif

    <h1 class="text-2xl font-bold mb-6">Hello From Pages</h1>

    <div class="space-y-6">
        @forelse ($items as $item)
            @php($t = $item['translation'])

            <div class="border-b border-slate-200 pb-4">
                <h2 class="text-xl font-semibold text-slate-900">
                    <a href="{{ url("/pages/{$t->slug}") }}" class="hover:underline">
                        {{ $t->title }}
                    </a>

                    @if ($item['english_only'])
                        <span class="ml-2 text-xs font-medium text-slate-500">
                            ({{ __('pages.only_available_in_english') }})
                        </span>
                    @endif
                </h2>

                <p class="text-sm text-slate-600">
                    {{ $t->description }}
                </p>
            </div>
        @empty
            <p class="text-slate-600">No pages available.</p>
        @endforelse
    </div>
</div>
