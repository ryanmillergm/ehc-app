<?php

namespace App\Livewire\Pages;

use App\Models\PageTranslation;
use Livewire\Component;

class ShowPage extends Component
{
    public $translation;

    public function mount($slug) {
        $this->translation = PageTranslation::with('page')->where('slug', $slug)->first();
    }

    public function render()
    {
        return view('livewire.pages.show-page');
    }
}
