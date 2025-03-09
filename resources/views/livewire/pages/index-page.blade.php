<div>
    <h1>Hello From Pages</h1>
            
        @foreach ($this->translations as $translation)
            {{ $translation->title }}
        @endforeach
        
</div>
