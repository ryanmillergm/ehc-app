<?php

namespace App\Filament\Resources\ImageTypes\Pages;

use App\Filament\Resources\ImageTypes\ImageTypeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditImageType extends EditRecord
{
    protected static string $resource = ImageTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
