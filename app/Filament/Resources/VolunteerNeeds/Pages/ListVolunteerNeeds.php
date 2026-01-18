<?php

namespace App\Filament\Resources\VolunteerNeeds\Pages;

use App\Filament\Resources\VolunteerNeeds\VolunteerNeedResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVolunteerNeeds extends ListRecords
{
    protected static string $resource = VolunteerNeedResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
