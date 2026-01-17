<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Pages\AdminDocumentation;
use Filament\Widgets\Widget;

class DocumentationWidget extends Widget
{
    protected string $view = 'filament.widgets.documentation-widget';

    protected int|string|array $columnSpan = 'full';

    public function getDocumentationUrl(): string
    {
        return AdminDocumentation::getUrl();
    }
}
