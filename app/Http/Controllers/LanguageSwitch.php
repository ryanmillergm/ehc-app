<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class LanguageSwitch extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, $language_code)
    {
        app()->setLocale($language_code);
        session()->put('locale', $language_code);

        $language = getLanguage($language_code);

        session()->put('language_id', $language->id);

        $prev_path = parse_url($request->session()->previousUrl());

        if (isset($prev_path["path"]) && $prev_path["path"] == '/lang/' . $language_code) {
            return redirect('/')->with('success', __('flash-messages.language_updated'));
        } else {
            return back()->with('success', __('flash-messages.language_updated'));
        }
    }
}
