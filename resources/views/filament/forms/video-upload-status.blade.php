<div
    x-data="{ uploading: false, processing: false, progress: 0 }"
    x-on:livewire-upload-start.window="uploading = true; processing = false; progress = 0"
    x-on:livewire-upload-progress.window="progress = $event.detail.progress"
    x-on:livewire-upload-finish.window="uploading = false; processing = true; setTimeout(() => processing = false, 1400)"
    x-on:livewire-upload-error.window="uploading = false; processing = false"
    class="space-y-2"
>
    <template x-if="uploading">
        <div class="rounded-lg border border-primary-200 bg-primary-50 p-3 text-sm text-primary-900">
            <div class="flex items-center justify-between">
                <span class="inline-flex items-center gap-2">
                    <span class="inline-block h-2 w-2 animate-pulse rounded-full bg-primary-600"></span>
                    Uploading video...
                </span>
                <span class="font-semibold" x-text="progress + '%'"></span>
            </div>
            <div class="mt-2 h-2 w-full overflow-hidden rounded bg-primary-100">
                <div class="h-full bg-primary-600 transition-all duration-200" :style="'width: ' + progress + '%'"></div>
            </div>
        </div>
    </template>

    <template x-if="processing">
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
            <span class="inline-flex items-center gap-2">
                <span class="inline-block h-2 w-2 animate-pulse rounded-full bg-amber-600"></span>
                Processing video metadata...
            </span>
        </div>
    </template>
</div>

