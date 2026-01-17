<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class EmailSystemHelp extends Page
{
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        // Spatie Permissions
        return $user->can('admin.panel');
    }

    protected string $view = 'filament.pages.email-system-help';
}
