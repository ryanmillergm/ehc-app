<?php

namespace App\Enums;

enum PageTemplate: string
{
    case Standard = 'standard';
    case Campaign = 'campaign';
    case Story = 'story';
    case Immersive = 'immersive';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::Standard->value => 'Standard',
            self::Campaign->value => 'Campaign',
            self::Story->value => 'Story',
            self::Immersive->value => 'Immersive',
        ];
    }
}
