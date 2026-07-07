<?php

namespace App\Enums;

enum HeroStyle: string
{
    case Contained = 'contained';
    case FullBleed = 'full_bleed';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::Contained->value => 'Contained',
            self::FullBleed->value => 'Full Bleed',
        ];
    }
}

