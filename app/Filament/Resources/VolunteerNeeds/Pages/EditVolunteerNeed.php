<?php

namespace App\Filament\Resources\VolunteerNeeds\Pages;

use App\Filament\Resources\VolunteerNeeds\VolunteerNeedResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditVolunteerNeed extends EditRecord
{
    protected static string $resource = VolunteerNeedResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
