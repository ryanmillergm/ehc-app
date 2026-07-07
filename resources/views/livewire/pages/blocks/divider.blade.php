@if (($data['style'] ?? 'line') === 'space')
    <div class="h-10"></div>
@else
    <div class="relative py-2">
        <hr class="border-slate-200" />
    </div>
@endif
