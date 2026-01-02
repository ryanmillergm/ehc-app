<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\URL;

class Localization
{
    /**
     * Handle an incoming request.
     *
     * @param Closure(Request):Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $default_locale = app()->getLocale();
        $locale = $request->session()->get('locale', $default_locale);

        App::setlocale($locale);
        $request->session()->put('locale', $locale);

        $language = getLanguage($locale) ?? getLanguage($default_locale);

        if($language) {
            session()->put('language_id', $language->id);
        }

        // URL::defaults(['locale' => $request->segment(1)]);

        return $next($request);
    }
}
