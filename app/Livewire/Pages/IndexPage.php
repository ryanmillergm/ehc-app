<?php

namespace App\Livewire\Pages;

use App\Models\Page;
use Livewire\Attributes\Computed;
use Livewire\Component;

class IndexPage extends Component
{

    #[Computed()]
    public function pages() {
        $pages = Page::pageTranslationsByLanguage();

        return $pages;
    }

    #[Computed()]
    public function translations() {
        $pages = $this->pages;
        $translations = [];

        foreach($pages as $page) {
            array_push($translations, $page->pageTranslations->first());
        }

        return $translations;
    }

    public function render()
    {
        return view('livewire.pages.index-page');
    }
}
