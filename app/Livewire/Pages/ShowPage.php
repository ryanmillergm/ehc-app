<?php

namespace App\Livewire\Pages;

use App\Models\Language;
use App\Models\PageTranslation;
use Livewire\Attributes\Session;
use Livewire\Component;

class ShowPage extends Component
{
    public $translation;

    #[Session(key: 'redirect_from_translation')]
    public $redirectFromTranslation = false;  

    public function mount($slug) {
        $this->translation = $this->findTranslationBySlugOrDefault($slug);
    }

    public function findTranslationBySlugOrDefault($slug)
    {
        $translationQuery = PageTranslation::translationBySlug($slug);
        $translation = $translationQuery->get()->first();
        
        if (!$translation || !$translation->page->is_active) {
            session()->flash('status', "Sorry. The page you were looking for does not exist.");
            return $this->redirectToPages($slug);
        }
        
        if ($this->redirectFromTranslation) {
            $this->toggleRedirectFromTranslation();
            
            return $translation;
        }

        $language = Language::find(session('language_id')) ?? getLanguage();
        
        // If Translation By Slug Doesn't Match Current Language, find which translation it should dispaly.
        if ($translation->language_id !== $language->id) {
            
            $this->findTranslationToDisplay($slug, $translation, $language);
        }

        return $translation;
    }

    public function findTranslationToDisplay($slug, $translation, $language)
    {
        $page = $translation->page;
            $translation_for_current_language = $page->pageTranslations->where('language_id', $language->id)->first();

            // If translation found for current Language redirect to that translation's slug
            if ($translation_for_current_language) {
                session()->flash('status', "Found Page for {$language->title} language.");
                // $this->translation = $translation_for_current_language;

                $this->toggleRedirectFromTranslation();
                return $this->redirect('/pages/' . $translation_for_current_language->slug, navigate: true);  
            } 
            
            $default_language = Language::first();
            session()->flash('status', "{$language->title} page not available.");
            
            $translation_for_default_language = $page->pageTranslations->where('language_id', $default_language->id)->first();
            
            // If there is a translation for default language, redirect to translation's slug
            if ($translation_for_default_language) {
                session()->flash('status', "Found {$default_language->title} page intead.");
                // $this->translation = $translation_for_default_language;
                $this->toggleRedirectFromTranslation();
                return $this->redirect('/pages/' . $translation_for_default_language->slug, navigate: true);
            }

            // If current and default language translations are not available, return current slug's translation in different language or if no translation exists at all, redirect to pages index.
            if($translation) {
                $translation_language = Language::find($translation->language_id);

                session()->flash('status', "Found {$translation_language->title} page intead.");
                $this->translation = $translation;
            } else {
                session()->flash('status', "Sorry. The page you were looking for does not exist");
                return $this->redirectToPages($slug);
            }

        return $translation;
    }

    public function toggleRedirectFromTranslation()
    {
        $this->redirectFromTranslation = !$this->redirectFromTranslation;
    }

    public function redirectToPages($slug) 
    {
        session()->flash('status', "Sorry, the page '{$slug}' does not exist.");
        $this->redirect(IndexPage::class, navigate: true);
    }

    public function render()
    {
        return view('livewire.pages.show-page');
    }
}
