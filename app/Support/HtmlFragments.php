<?php

namespace App\Support;

class HtmlFragments
{
    public static function bodyInner(?string $html): ?string
    {
        if ($html === null) return null;

        $html = trim($html);
        if ($html === '') return '';

        // Fast path: extract body inner HTML
        if (preg_match('/<body\b[^>]*>([\s\S]*?)<\/body>/i', $html, $m)) {
            return trim($m[1]);
        }

        // Fallback: extract html inner HTML (rare but helpful)
        if (preg_match('/<html\b[^>]*>([\s\S]*?)<\/html>/i', $html, $m)) {
            return trim($m[1]);
        }

        return $html; // already a fragment
    }
}
