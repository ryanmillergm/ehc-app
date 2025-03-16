<?php

namespace App\Livewire\Pages;

use App\Models\Language;
use App\Models\PageTranslation;
use Livewire\Component;

class ShowPage extends Component
{
    public $translation;

    public function mount($slug) {
        $this->translation = $this->findTranslationBySlugOrDefault($slug);
    }

    public function findTranslationBySlugOrDefault($slug)
    {
        $translationQuery = PageTranslation::translationBySlug($slug);
        $translation = $translationQuery->get()->first();
        
        if (!$translation || !$translation->page->is_active) {
            return $this->redirectToPages($slug);
        }
        
        $language = Language::find(session('language_id')) ?? getLanguage();
        
        // Translation By Slug Doesn't Match Current Language
        if ($translation->language_id !== $language->id) {
            $page = $translation->page;
            $translation_for_current_language = $page->pageTranslations->where('language_id', $language->id)->first();
            // If translation found for current Language redirect to translations slug
            if ($translation_for_current_language) {
                // session()->flash('status', `Found Page for $language->title language.`);
                // $this->translation = $translation_for_current_language;
                return $this->redirect('/pages/' . $translation_for_current_language->slug, navigate: true);  
            } 
            
            $default_language = Language::first();
            // session()->flash('status', `$language->title page not available.`);
            
            $translation_for_default_language = $page->pageTranslations->where('language_id', $default_language->id)->first();
            
            // If translation for default language redirect to translations slug
            if ($translation_for_default_language) {
                // session()->flash('status', `Found $default_language->title page intead.`);
                // $this->translation = $translation_for_default_language;
                return $this->redirect('/pages/' . $translation_for_default_language->slug, navigate: true);
            }

            // If current and default language translations are not available, return original transaltion for slug else redirect to pages index
            if($translation) {
                $translation_language = Language::find($translation->language_id);

                // session()->flash('status', `Found $translation_language->title page intead.`);
                $this->translation = $translation;
            } else {
                return $this->redirectToPages($slug);
            }
        }

        return $translation;
    }

    public function redirectToPages($slug) 
    {
        // session()->flash('status', `Sorry, the page "$slug" does not exist.`);

        $this->redirect(IndexPage::class, navigate: true);
    }

    public function render()
    {
        return view('livewire.pages.show-page');
    }
}
