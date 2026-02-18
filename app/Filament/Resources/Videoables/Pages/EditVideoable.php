<?php

namespace App\Filament\Resources\Videoables\Pages;

use App\Filament\Resources\Videoables\VideoableResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditVideoable extends EditRecord
{
    protected static string $resource = VideoableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
