<?php

namespace App\Http\Controllers;

use App\Models\RouteSeo;
use App\Services\Seo\RouteSeoResolver;
use Illuminate\View\View;

class EmailSubscribeController extends Controller
{
    public function __invoke(RouteSeoResolver $seo): View
    {
        return view('emails.subscribe', [
            'seo' => $seo->resolve(RouteSeo::ROUTE_EMAILS_SUBSCRIBE),
        ]);
    }
}
