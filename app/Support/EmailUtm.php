<?php

namespace App\Support;

use Illuminate\Support\Str;

class EmailUtm
{
    public static function apply(string $html, array $utm): string
    {
        if (! filled($html)) return $html;

        libxml_use_internal_errors(true);

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        foreach ($dom->getElementsByTagName('a') as $a) {
            $href = $a->getAttribute('href');
            if (! $href) continue;

            if (Str::startsWith($href, ['mailto:', 'tel:', '#', 'javascript:'])) continue;

            $parts = parse_url($href);
            if (! $parts) continue;

            $query = [];
            parse_str($parts['query'] ?? '', $query);

            foreach ($utm as $k => $v) {
                if (! isset($query[$k]) && filled($v)) {
                    $query[$k] = $v;
                }
            }

            $parts['query'] = http_build_query($query);
            $a->setAttribute('href', self::unparseUrl($parts));
        }

        return $dom->saveHTML();
    }

    private static function unparseUrl(array $p): string
    {
        $scheme   = isset($p['scheme']) ? $p['scheme'] . '://' : '';
        $user     = $p['user'] ?? '';
        $pass     = isset($p['pass']) ? ':' . $p['pass'] : '';
        $auth     = ($user || $pass) ? "$user$pass@" : '';
        $host     = $p['host'] ?? '';
        $port     = isset($p['port']) ? ':' . $p['port'] : '';
        $path     = $p['path'] ?? '';
        $query    = isset($p['query']) && $p['query'] !== '' ? '?' . $p['query'] : '';
        $fragment = isset($p['fragment']) ? '#' . $p['fragment'] : '';

        // Handles relative URLs too (no scheme/host)
        return $scheme . $auth . $host . $port . $path . $query . $fragment;
    }
}
