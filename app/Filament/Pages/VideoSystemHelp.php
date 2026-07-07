<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class VideoSystemHelp extends Page
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

        return $user->can('admin.panel');
    }

    protected string $view = 'filament.pages.video-system-help';
}
