<?php

namespace App\Http\Controllers\Seo;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

class RobotsController extends Controller
{
    public function __invoke(): Response
    {
        $sitemapUrl = rtrim(config('app.url'), '/') . '/sitemap.xml';
        $disallowNonProd = (bool) config('seo.robots_disallow_non_production', true);
        $disallow = ($disallowNonProd && ! app()->environment('production')) ? '/' : '';

        $content = "User-agent: *\nDisallow: {$disallow}\nSitemap: {$sitemapUrl}\n";

        return response($content, 200, ['Content-Type' => 'text/plain']);
    }
}
