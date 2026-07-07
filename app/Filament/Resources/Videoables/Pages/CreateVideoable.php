<?php

namespace App\Filament\Resources\Videoables\Pages;

use App\Filament\Resources\Videoables\VideoableResource;
use Filament\Resources\Pages\CreateRecord;

class CreateVideoable extends CreateRecord
{
    protected static string $resource = VideoableResource::class;
}
