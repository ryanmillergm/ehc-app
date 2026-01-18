<?php

namespace App\Filament\Resources\VolunteerApplications\Pages;

use App\Filament\Resources\VolunteerApplications\VolunteerApplicationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVolunteerApplications extends ListRecords
{
    protected static string $resource = VolunteerApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
