<?php

namespace App\Livewire;

use App\Models\Language;
use Livewire\Component;

class Navbar extends Component
{
    public $languages;
    public ?Language $currentLanguage = null;

    public function mount(): void
    {
        $this->languages = Language::orderBy('title')->get();

        $this->currentLanguage =
            Language::find(session('language_id'))
            ?? $this->languages->first();
    }

    public function switchLanguage(string $code): void
    {
        $language =
            $this->languages->firstWhere('locale', $code)
            ?? Language::where('locale', $code)->first();

        if (! $language) {
            return;
        }

        // Persist language like your LanguageSwitch controller
        app()->setLocale($language->locale);
        session()->put('locale', $language->locale);
        session()->put('language_id', $language->id);

        $this->currentLanguage = $language;

        // Tell ShowPage (and any other listeners) to re-resolve
        $this->dispatch('language-switched', code: $language->locale);
    }

    public function render()
    {
        return view('livewire.navbar');
    }
}
