<?php

namespace App\Enums;

enum HeroHeight: string
{
    case H70 = '70';
    case H80 = '80';
    case H100 = '100';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::H70->value => '70vh',
            self::H80->value => '80vh',
            self::H100->value => '100vh',
        ];
    }
}

