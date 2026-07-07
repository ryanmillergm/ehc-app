<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use Filament\Pages\Page;

class PageAuthoringHelp extends Page
{
    protected string $view = 'filament.pages.page-authoring-help';

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

        return $user->can('admin.panel');
    }
}
