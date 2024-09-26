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

         return back()->with('success', __('flash-messages.language_updated'));
    }
}
