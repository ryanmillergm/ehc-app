<div>
    @if (session('status'))
        <div class="text-center bg-green-700 text-gray-200">
            {{ session('status') }}
        </div>
    @endif

    <h1>This is the show page</h1>
    
    <h1>{{ $this->translation->title }}</h1>
    <p>{{ $this->translation->description }}</p>
    <div>{!! $this->translation->content !!}</div>
</div>
