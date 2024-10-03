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
                return Language::find($id);
            } else if ($locale) {
                return Language::where('iso_code', $locale)->first();
            } else {
                return Language::first();
            }
        }
    }

?>
