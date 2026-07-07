<?php

namespace App\Http\Controllers\Seo;

use App\Http\Controllers\Controller;
use App\Services\Seo\SitemapService;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(SitemapService $sitemap): Response
    {
        $items = $sitemap->urls()->map(function (array $item) {
            $loc = htmlspecialchars($item['loc'], ENT_XML1);
            $lastmod = $item['lastmod']
                ? '<lastmod>' . htmlspecialchars($item['lastmod'], ENT_XML1) . '</lastmod>'
                : '';

            return "<url><loc>{$loc}</loc>{$lastmod}</url>";
        })->implode('');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
            . $items
            . '</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }
}
