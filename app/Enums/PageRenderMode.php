<?php

namespace App\Enums;

enum PageRenderMode: string
{
    case Template = 'template';
    case Blocks = 'blocks';
    case Custom = 'custom';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::Template->value => 'Template',
            self::Blocks->value => 'Block Builder',
            self::Custom->value => 'Custom HTML',
        ];
    }
}

