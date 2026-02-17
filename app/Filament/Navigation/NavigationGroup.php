<?php

namespace App\Filament\Navigation;

use Filament\Support\Contracts\HasLabel;

enum NavigationGroup implements HasLabel
{
    case Email;
    case Forms;
    case Pages;
    case Images;
    case Donations;
    case UserSettings;
    case GeneralSettings;

    public function getLabel(): string
    {
        return match ($this) {
            self::Email => 'Email',
            self::Forms => 'Forms',
            self::Pages => 'Pages',
            self::Images => 'Images',
            self::Donations => 'Donations',
            self::UserSettings => 'User Settings',
            self::GeneralSettings => 'General Settings',
        };
    }
}
