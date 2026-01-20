<?php

namespace App\Support;

use App\Models\ApplicationForm;
use Stevebauman\Purify\Facades\Purify;

trait SanitizesApplicationFormThankYou
{
    protected function sanitizeThankYou(array $data): array
    {
        $format = $data['thank_you_format'] ?? ApplicationForm::THANK_YOU_TEXT;

        if (! in_array($format, [ApplicationForm::THANK_YOU_HTML, ApplicationForm::THANK_YOU_WYSIWYG], true)) {
            return $data;
        }

        $html = (string) ($data['thank_you_content'] ?? '');

        $data['thank_you_content'] = Purify::config('application_form_thank_you')->clean($html);

        return $data;
    }
}
