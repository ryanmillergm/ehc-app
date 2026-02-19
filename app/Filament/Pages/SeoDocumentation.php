<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class SeoDocumentation extends Page
{
    protected string $view = 'filament.pages.seo-documentation';

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
