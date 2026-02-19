<?php

namespace App\Filament\Resources\RouteSeoResource\Pages;

use App\Filament\Resources\RouteSeoResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRouteSeo extends CreateRecord
{
    protected static string $resource = RouteSeoResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
