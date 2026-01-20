<?php

namespace App\Filament\Resources\ApplicationForms\Pages;

use App\Filament\Resources\ApplicationForms\ApplicationFormResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListApplicationForms extends ListRecords
{
    protected static string $resource = ApplicationFormResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
