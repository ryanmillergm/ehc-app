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
        // Persist locale first
        app()->setLocale($language_code);
        session()->put('locale', $language_code);

        $language = getLanguage($language_code);
        session()->put('language_id', $language->id);

        // Inject language name into the translated message
        $message = __('flash-messages.language_updated', [
            'language' => $language->name, // e.g. "English", "Español"
        ]);

        // If called via fetch/AJAX, return JSON so JS can show the correct translation
        if ($request->expectsJson() || $request->ajax() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->json([
                'style'   => 'success',
                'message' => $message,
                'locale'  => $language_code,
            ]);
        }

        $prev_path = parse_url($request->session()->previousUrl());

        $redirect = (isset($prev_path["path"]) && $prev_path["path"] == '/lang/' . $language_code)
            ? redirect('/')
            : back();

        return $redirect
            ->with('flash.banner', $message)
            ->with('flash.bannerStyle', 'success');
    }
}
