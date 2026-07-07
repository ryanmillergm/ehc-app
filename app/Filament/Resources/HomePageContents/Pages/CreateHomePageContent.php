<?php

namespace App\Filament\Resources\HomePageContents\Pages;

use App\Filament\Resources\HomePageContents\HomePageContentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateHomePageContent extends CreateRecord
{
    protected static string $resource = HomePageContentResource::class;
}
