<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class EmailSystemHelp extends Page
{
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    protected string $view = 'filament.pages.email-system-help';
}
