@php
    $statePath = $getStatePath();
    $htmlPath  = str_replace('design_json', 'design_html', $statePath);
    $cssPath   = str_replace('design_json', 'design_css', $statePath);
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        wire:ignore
        x-data="grapesEmailBuilder({
            height: '700px',
            project: $wire.entangle('{{ $statePath }}'),

            // ✅ IMPORTANT: third param false = don't talk to server
            setProject: (v) => $wire.set('{{ $statePath }}', v, false),
            setHtml:    (v) => $wire.set('{{ $htmlPath }}', v, false),
            setCss:     (v) => $wire.set('{{ $cssPath }}', v, false),
        })"
        x-init="init()"
        class="rounded-xl border border-gray-200 dark:border-gray-800 overflow-hidden"
        :class="{ 'fixed inset-4 z-9999 bg-white dark:bg-gray-950 shadow-2xl': fullscreen }"
    >
        <div class="flex items-center justify-between px-3 py-2 border-b border-gray-200 dark:border-gray-800">
            <div class="text-xs text-gray-500">
                Drag blocks → double-click text to edit
            </div>

            <button type="button"
                class="px-2 py-1 text-xs rounded-md border border-gray-200 dark:border-gray-800"
                @click="toggleFullscreen()"
            >
                <span x-text="fullscreen ? 'Exit fullscreen' : 'Fullscreen'"></span>
            </button>
        </div>

        <div class="flex" style="height: 700px" :style="fullscreen ? 'height: calc(100vh - 4rem)' : ''">
            <div class="w-56 border-r border-gray-200 dark:border-gray-800 overflow-auto" x-ref="blocks"></div>

            <div class="flex-1 min-w-0" x-ref="canvas"></div>

            <div class="w-72 border-l border-gray-200 dark:border-gray-800 overflow-auto">
                <div class="p-2 text-xs text-gray-500 border-b border-gray-200 dark:border-gray-800">Layers</div>
                <div x-ref="layers"></div>

                <div class="p-2 text-xs text-gray-500 border-b border-gray-200 dark:border-gray-800">Styles</div>
                <div x-ref="styles"></div>

                <div class="p-2 text-xs text-gray-500 border-b border-gray-200 dark:border-gray-800">Settings</div>
                <div x-ref="traits"></div>
            </div>
        </div>
    </div>
</x-dynamic-component>
