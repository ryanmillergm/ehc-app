<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Navigation\NavigationGroup;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class AdminDocumentation extends Page
{
    protected static ?string $navigationLabel = 'Documentation';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;
    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::GeneralSettings;
    protected static ?int $navigationSort = 999;

    protected string $view = 'filament.pages.admin-documentation';

    /**
     * Optional: lock this to admins only.
     * Adjust this to match your auth/permissions setup.
     */
    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        // Spatie Permissions
        return $user->can('admin.panel');
    }
}
