<?php

namespace App\Filament\Resources\ImageGroupables\Pages;

use App\Filament\Resources\ImageGroupables\ImageGroupableResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditImageGroupable extends EditRecord
{
    protected static string $resource = ImageGroupableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
