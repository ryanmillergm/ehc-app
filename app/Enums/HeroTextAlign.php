<?php

namespace App\Enums;

enum HeroTextAlign: string
{
    case Left = 'left';
    case Center = 'center';
    case Right = 'right';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::Left->value => 'Left',
            self::Center->value => 'Center',
            self::Right->value => 'Right',
        ];
    }
}

