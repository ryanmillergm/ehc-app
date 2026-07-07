<x-filament::section>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="space-y-1">
            <div class="text-sm font-semibold text-gray-900">
                Admin Documentation
            </div>
            <div class="text-xs text-gray-600">
                Quick reference for content, media (images/videos), email workflows, and operations playbooks.
            </div>
        </div>

        <div class="flex items-center gap-2">
            <x-filament::button
                tag="a"
                :href="$this->getDocumentationUrl()"
                icon="heroicon-o-book-open"
            >
                Open Docs
            </x-filament::button>
        </div>
    </div>
</x-filament::section>
