<?php

namespace App\Filament\Org\Resources\ChildResource\Pages;

use Filament\Actions\EditAction;
use App\Filament\Org\Resources\ChildResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewChild extends ViewRecord
{
    protected static string $resource = ChildResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
