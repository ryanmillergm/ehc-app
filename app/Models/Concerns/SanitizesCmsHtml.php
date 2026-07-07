<?php

namespace App\Models\Concerns;

use Stevebauman\Purify\Facades\Purify;

trait SanitizesCmsHtml
{
    protected function sanitizeCmsField(?string $value): ?string
    {
        return $this->sanitizeCmsFieldByProfile($value, 'cms_rich_text');
    }

    protected function sanitizeCmsFieldByProfile(?string $value, string $profile): ?string
    {
        if (! is_string($value)) {
            return $value;
        }

        return Purify::config($profile)->clean($this->stripBlockedScriptContent($value));
    }

    protected function stripBlockedScriptContent(?string $value): ?string
    {
        if (! is_string($value)) {
            return $value;
        }

        $withoutScriptTags = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $value) ?? $value;
        $withoutEventHandlers = preg_replace('/\s+on[a-z]+\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/i', '', $withoutScriptTags) ?? $withoutScriptTags;

        return preg_replace('/javascript:/i', '', $withoutEventHandlers) ?? $withoutEventHandlers;
    }
}
