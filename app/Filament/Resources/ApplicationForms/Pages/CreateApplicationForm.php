<?php

namespace App\Filament\Resources\ApplicationForms\Pages;

use App\Filament\Resources\ApplicationForms\ApplicationFormResource;
use App\Models\ApplicationForm;
use Filament\Resources\Pages\CreateRecord;
use Stevebauman\Purify\Facades\Purify;

class CreateApplicationForm extends CreateRecord
{
    protected static string $resource = ApplicationFormResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        [$format, $content] = $this->resolveThankYouContent($data);

        $data['thank_you_format'] = $format;
        $data['thank_you_content'] = $content;

        unset($data['thank_you_text'], $data['thank_you_wysiwyg'], $data['thank_you_html']);

        return $data;
    }

    private function resolveThankYouContent(array $data): array
    {
        $format = $data['thank_you_format'] ?? ApplicationForm::THANK_YOU_WYSIWYG;

        $raw = match ($format) {
            ApplicationForm::THANK_YOU_TEXT    => $data['thank_you_text'] ?? '',
            ApplicationForm::THANK_YOU_WYSIWYG => $data['thank_you_wysiwyg'] ?? '',
            ApplicationForm::THANK_YOU_HTML    => $data['thank_you_html'] ?? '',
            default => '',
        };

        if ($format === ApplicationForm::THANK_YOU_WYSIWYG) {
            $raw = $this->richEditorStateToHtml($raw);
        }

        $raw = is_string($raw) ? $raw : '';

        if (in_array($format, [ApplicationForm::THANK_YOU_WYSIWYG, ApplicationForm::THANK_YOU_HTML], true)) {
            $raw = $this->purifyThankYouHtml($raw);
        }

        return [$format, $raw];
    }

    private function richEditorStateToHtml(mixed $state): string
    {
        if (is_string($state)) {
            return $state;
        }

        if (! is_array($state)) {
            return '';
        }

        $blocks = $state['blocks'] ?? $state;

        if (! is_array($blocks)) {
            return '';
        }

        $html = '';

        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }

            $type = $block['type'] ?? 'paragraph';
            $data = $block['data'] ?? [];
            $text = is_array($data) ? ($data['text'] ?? '') : '';
            $text = is_string($text) ? $text : '';

            $html .= match ($type) {
                'header' => '<h2>' . $text . '</h2>',
                'paragraph' => '<p>' . $text . '</p>',
                'blockquote' => '<blockquote>' . $text . '</blockquote>',
                default => '<p>' . $text . '</p>',
            };
        }

        return $html;
    }

    private function purifyThankYouHtml(string $dirty): string
    {
        return Purify::config('application_form_thank_you')->clean($dirty);
    }
}
