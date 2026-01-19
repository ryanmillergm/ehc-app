<?php

namespace App\Filament\Resources\ApplicationForms\Pages;

use App\Filament\Resources\ApplicationForms\ApplicationFormResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditApplicationForm extends EditRecord
{
    protected static string $resource = ApplicationFormResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
