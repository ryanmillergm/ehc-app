<?php

namespace App\Filament\Resources\Imageables\Pages;

use App\Filament\Resources\Imageables\ImageableResource;
use Filament\Resources\Pages\CreateRecord;

class CreateImageable extends CreateRecord
{
    protected static string $resource = ImageableResource::class;
}
