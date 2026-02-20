<?php

namespace App\Http\Controllers;

use App\Services\Seo\RouteSeoResolver;
use App\Support\Seo\RouteSeoTarget;
use Illuminate\View\View;

class EmailSubscribeController extends Controller
{
    public function __invoke(RouteSeoResolver $seo): View
    {
        return view('emails.subscribe', [
            'seo' => $seo->resolve(RouteSeoTarget::EMAILS_SUBSCRIBE),
        ]);
    }
}
