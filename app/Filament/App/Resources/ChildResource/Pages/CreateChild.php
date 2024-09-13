<?php

namespace App\Filament\App\Resources\ChildResource\Pages;

use App\Filament\App\Resources\ChildResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateChild extends CreateRecord
{
    protected static string $resource = ChildResource::class;
}
