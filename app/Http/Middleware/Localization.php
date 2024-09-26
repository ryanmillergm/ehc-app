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
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $default_locale = app()->getLocale();
        $locale = $request->session()->get('locale', $default_locale);

        App::setlocale($locale);
        $request->session()->put('locale', $locale);

        // URL::defaults(['locale' => $request->segment(1)]);

        return $next($request);
    }
}
