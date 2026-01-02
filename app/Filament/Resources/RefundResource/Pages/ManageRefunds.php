<?php

namespace App\Filament\Resources\RefundResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\RefundResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageRefunds extends ManageRecords
{
    protected static string $resource = RefundResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
