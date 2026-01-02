<?php

namespace App\Filament\Navigation;

use Filament\Support\Contracts\HasLabel;

enum NavigationGroup implements HasLabel
{
    case Email;
    case Pages;
    case Donations;
    case UserSettings;
    case GeneralSettings;

    public function getLabel(): string
    {
        return match ($this) {
            self::Email => 'Email',
            self::Pages => 'Pages',
            self::Donations => 'Donations',
            self::UserSettings => 'User Settings',
            self::GeneralSettings => 'General Settings',
        };
    }
}
