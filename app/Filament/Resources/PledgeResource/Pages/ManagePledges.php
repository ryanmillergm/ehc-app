<?php

namespace App\Filament\Resources\PledgeResource\Pages;

use App\Filament\Resources\PledgeResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManagePledges extends ManageRecords
{
    protected static string $resource = PledgeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
