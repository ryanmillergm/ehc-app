<?php

namespace App\Filament\Resources\EmailLists\Pages;

use App\Filament\Resources\EmailLists\EmailListResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEmailList extends CreateRecord
{
    protected static string $resource = EmailListResource::class;
}
