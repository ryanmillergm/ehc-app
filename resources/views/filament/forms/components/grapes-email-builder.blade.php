@php
    $recordKey = optional($getRecord())->getKey() ?? 'create';
    $lwId = method_exists($getLivewire(), 'getId') ? $getLivewire()->getId() : 'lw';
    $instanceKey = "gjs-{$recordKey}-{$lwId}";

    $initialHtml = $getRecord()?->design_html ?? '';
    $initialCss  = $getRecord()?->design_css ?? '';

    $initialJson = $getRecord()?->design_json ?? '';
    if (is_array($initialJson)) {
        $initialJson = json_encode($initialJson);
    }
@endphp


<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        wire:ignore
        wire:key="gjs-root-{{ $instanceKey }}"
        data-gjs-email-builder="1"
        data-gjs-key="{{ $instanceKey }}"
        data-gjs-html-key="design_html"
        data-gjs-css-key="design_css"
        data-gjs-json-key="design_json"
        class="rounded-xl border border-gray-200 dark:border-gray-800 overflow-hidden bg-white dark:bg-gray-950"
        style="height: 700px;"
    >
        {{-- Initial payload --}}
        <textarea data-gjs-initial-html class="hidden">{{ $initialHtml }}</textarea>
        <textarea data-gjs-initial-css class="hidden">{{ $initialCss }}</textarea>
        <textarea data-gjs-initial-json class="hidden">{{ $initialJson }}</textarea>

        {{-- Toolbar --}}
        <div class="flex items-center justify-between px-3 py-2 border-b border-gray-200 dark:border-gray-800">
            <div class="text-xs text-gray-500">Email Designer</div>

            <button
                type="button"
                data-gjs-fullscreen-toggle
                class="text-xs px-3 py-1 rounded-lg border border-gray-200 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-900"
            >
                Fullscreen
            </button>
        </div>

        {{-- Layout --}}
        <div class="flex" data-gjs-layout style="height: calc(700px - 41px);">
            <div class="w-56 border-r border-gray-200 dark:border-gray-800 overflow-auto" data-gjs-blocks></div>
            <div class="flex-1 min-w-0" data-gjs-canvas></div>

            <div class="w-72 border-l border-gray-200 dark:border-gray-800 overflow-auto">
                <div class="p-2 text-xs text-gray-500 border-b border-gray-200 dark:border-gray-800">Layers</div>
                <div data-gjs-layers></div>

                <div class="p-2 text-xs text-gray-500 border-b border-gray-200 dark:border-gray-800">Styles</div>
                <div data-gjs-styles></div>

                <div class="p-2 text-xs text-gray-500 border-b border-gray-200 dark:border-gray-800">Settings</div>
                <div data-gjs-traits></div>
            </div>
        </div>
    </div>
</x-dynamic-component>
