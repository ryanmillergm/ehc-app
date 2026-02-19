<?php

namespace App\Filament\Resources\RouteSeoResource\Pages;

use App\Filament\Resources\RouteSeoResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewRouteSeo extends ViewRecord
{
    protected static string $resource = RouteSeoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
