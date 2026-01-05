<?php

namespace App\Support\Email;

use App\Support\HtmlFragments;
use Illuminate\Support\Str;
use TijsVerkoyen\CssToInlineStyles\CssToInlineStyles;

class EmailBodyCompiler
{
    public function __construct(
        protected CssToInlineStyles $inliner,
    ) {}

    /**
     * @return array{html:string,text:string}
     */
    public function compile(?string $designHtml, ?string $designCss): array
    {
        $designHtml = (string) ($designHtml ?? '');
        $designCss  = (string) ($designCss ?? '');

        // Always work with body-inner fragments to avoid GrapesJS/DOM weirdness.
        $fragment = HtmlFragments::bodyInner($designHtml);

        // Wrap so the inliner has a predictable DOM.
        $wrapped = '<!doctype html><html><head><meta charset="utf-8"></head><body>'
            . $fragment
            . '</body></html>';

        $inlined = $this->inliner->convert($wrapped, $designCss);

        // Store/send only the body inner HTML (your EmailCampaign model enforces this too).
        $finalHtml = HtmlFragments::bodyInner($inlined);

        return [
            'html' => $finalHtml,
            'text' => $this->textFromHtml($finalHtml),
        ];
    }

    public function textFromHtml(?string $html): string
    {
        if (! filled($html)) {
            return '';
        }

        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text);

        return Str::limit(trim((string) $text), 10000, '');
    }
}
