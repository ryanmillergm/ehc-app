<?php

namespace App\Enums;

enum HeroOverlay: string
{
    case None = 'none';
    case Light = 'light';
    case Medium = 'medium';
    case Dark = 'dark';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::None->value => 'None',
            self::Light->value => 'Light',
            self::Medium->value => 'Medium',
            self::Dark->value => 'Dark',
        ];
    }
}

