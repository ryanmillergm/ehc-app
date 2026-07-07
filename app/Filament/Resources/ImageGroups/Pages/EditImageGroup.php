<?php

namespace App\Filament\Resources\ImageGroups\Pages;

use App\Filament\Resources\ImageGroups\ImageGroupResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditImageGroup extends EditRecord
{
    protected static string $resource = ImageGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
