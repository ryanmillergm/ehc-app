<?php

namespace App\Enums;

enum HeroTextWidth: string
{
    case Narrow = 'narrow';
    case Normal = 'normal';
    case Wide = 'wide';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::Narrow->value => 'Narrow',
            self::Normal->value => 'Normal',
            self::Wide->value => 'Wide',
        ];
    }
}

