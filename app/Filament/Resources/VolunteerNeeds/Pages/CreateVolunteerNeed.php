<?php

namespace App\Filament\Resources\VolunteerNeeds\Pages;

use App\Filament\Resources\VolunteerNeeds\VolunteerNeedResource;
use Filament\Resources\Pages\CreateRecord;

class CreateVolunteerNeed extends CreateRecord
{
    protected static string $resource = VolunteerNeedResource::class;
}
