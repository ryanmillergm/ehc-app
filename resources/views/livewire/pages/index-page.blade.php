<div>
    @if (session('status'))
        <div class="text-center bg-green-700 text-gray-200">
            {{ session('status') }}
        </div>
    @endif
    
    <h1>Hello From Pages</h1>
    
            
        @foreach ($this->translations as $translation)
            <h1>{{ $translation->title }}</h1>
            <h1><a href="{{ url( "/pages/{$translation->slug}" ) }}" class="font-medium">{{ $translation->title }}</a></h1>
            <p>{{ $translation->description }}</p>
        @endforeach
        
</div>
