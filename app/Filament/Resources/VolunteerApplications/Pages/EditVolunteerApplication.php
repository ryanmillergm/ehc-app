<?php

namespace App\Filament\Resources\VolunteerApplications\Pages;

use App\Filament\Resources\VolunteerApplications\VolunteerApplicationResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditVolunteerApplication extends EditRecord
{
    protected static string $resource = VolunteerApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
