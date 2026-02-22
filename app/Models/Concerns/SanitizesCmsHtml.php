<?php

namespace App\Models\Concerns;

use Stevebauman\Purify\Facades\Purify;

trait SanitizesCmsHtml
{
    protected function sanitizeCmsField(?string $value): ?string
    {
        if (! is_string($value)) {
            return $value;
        }

        return Purify::config('cms_rich_text')->clean($value);
    }
}
