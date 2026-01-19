<?php

namespace App\Filament\Resources\ApplicationForms\Pages;

use App\Filament\Resources\ApplicationForms\ApplicationFormResource;
use App\Models\ApplicationForm;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Stevebauman\Purify\Facades\Purify;

class EditApplicationForm extends EditRecord
{
    protected static string $resource = ApplicationFormResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $format = $data['thank_you_format'] ?? ApplicationForm::THANK_YOU_TEXT;

        if ($format === ApplicationForm::THANK_YOU_HTML) {
            $dirty = (string) ($data['thank_you_content'] ?? '');

            try {
                $data['thank_you_content'] = Purify::config('application_form_thank_you')->clean($dirty);
            } catch (\Throwable $e) {
                $data['thank_you_content'] = Purify::clean($dirty);
            }
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
