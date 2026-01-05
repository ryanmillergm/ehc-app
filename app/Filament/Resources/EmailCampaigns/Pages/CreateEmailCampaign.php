<?php

namespace App\Filament\Resources\EmailCampaigns\Pages;

use App\Filament\Resources\EmailCampaigns\EmailCampaignResource;
use App\Support\HtmlFragments;
use App\Support\Email\EmailBodyCompiler;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;
use Filament\Support\Enums\Width;

class CreateEmailCampaign extends CreateRecord
{
    protected static string $resource = EmailCampaignResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        [$bodyHtml, $bodyText] = $this->compileBodyFromEditorData($data);

        $data['body_html'] = $bodyHtml;
        $data['body_text'] = $bodyText;

        unset($data['body_html_source']); // not a DB column

        return $data;
    }

    private function compileBodyFromEditorData(array $data): array
    {
        $editor = $data['editor'] ?? 'grapesjs';

        if ($editor === 'grapesjs') {
            $compiled = app(EmailBodyCompiler::class)->compile(
                (string) ($data['design_html'] ?? ''),
                (string) ($data['design_css'] ?? ''),
            );

            return [$compiled['html'], $compiled['text']];
        }

        if ($editor === 'html') {
            $raw = (string) ($data['body_html_source'] ?? $data['body_html'] ?? '');
            return $this->compileFromPossiblyFullDocument($raw);
        }

        // rich
        $html = HtmlFragments::bodyInner((string) ($data['body_html'] ?? ''));
        return [$html, self::toText($html)];
    }

    private function compileFromPossiblyFullDocument(string $raw): array
    {
        // Pull out <style> blocks and inline them
        $css = '';
        if (preg_match_all('/<style\b[^>]*>(.*?)<\/style>/is', $raw, $m)) {
            $css = implode("\n", $m[1]);
            $raw = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $raw);
        }

        $html = HtmlFragments::bodyInner($raw);

        $compiled = app(EmailBodyCompiler::class)->compile($html, $css);

        return [$compiled['html'], $compiled['text']];
    }

    private static function toText(?string $html): ?string
    {
        if (! filled($html)) return null;

        $text = strip_tags($html);
        $text = preg_replace('/\s+/', ' ', $text);

        return Str::limit(trim($text), 10000, '');
    }

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }
}
