<div x-data="{ mode: 'desktop' }" class="space-y-3">
    <div class="flex gap-2">
        <button type="button"
            class="px-3 py-1 text-sm rounded-md border"
            :class="mode === 'desktop' ? 'font-semibold' : ''"
            @click="mode='desktop'"
        >Desktop</button>

        <button type="button"
            class="px-3 py-1 text-sm rounded-md border"
            :class="mode === 'mobile' ? 'font-semibold' : ''"
            @click="mode='mobile'"
        >Mobile</button>
    </div>

    <div class="flex justify-center">
        <div
            class="w-full"
            :class="mode === 'mobile' ? 'max-w-[390px]' : 'max-w-[900px]'"
        >
            <iframe
                class="w-full rounded-lg border"
                style="height: 70vh;"
                srcdoc="{{ $srcdoc }}"
            ></iframe>
        </div>
    </div>
</div>
