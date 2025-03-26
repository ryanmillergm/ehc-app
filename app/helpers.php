<?php

use App\Models\Language;
use Illuminate\Support\Facades\Session;

    if (! function_exists('getLanguage')) {
        function getLanguage($locale = null, $id = null) {
            if (! $locale && ! $id) {
                $locale = Session::get('locale');
                $id = Session::get('language_id');
            }

            if ($id) {
                $language = Language::find($id);
            } else if ($locale) {
                $language =  Language::where('iso_code', $locale)->first();
            } else {
                $language =  Language::first();
            }

            return $language ?? Language::first();
        }
    }

?>
