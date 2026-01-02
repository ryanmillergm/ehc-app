<?php

namespace App\Filament\Resources\EmailCampaigns\Pages;

use App\Filament\Resources\EmailCampaigns\EmailCampaignResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;
use TijsVerkoyen\CssToInlineStyles\CssToInlineStyles;

class CreateEmailCampaign extends CreateRecord
{
    protected static string $resource = EmailCampaignResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        if (($data['editor'] ?? 'html') === 'grapesjs') {
            $html = $data['design_html'] ?? '';
            $css  = $data['design_css'] ?? '';

            // Don’t clobber if something went sideways
            if (filled($html)) {
                $inliner = new CssToInlineStyles();
                $data['body_html'] = $inliner->convert($html, $css);
                $data['body_text'] = self::toText($data['body_html']);
            }
        }

        return $data;
    }

    private static function toText(?string $html): ?string
    {
        if (! filled($html)) return null;

        $text = strip_tags($html);
        $text = preg_replace('/\s+/', ' ', $text);
        return Str::limit(trim($text), 10000, '');
    }
}
