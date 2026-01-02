<?php

namespace App\Filament\Resources\EmailCampaigns\Schemas;

use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class EmailCampaignForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('email_list_id')
                ->label('Email List')
                ->relationship(
                    name: 'list',
                    titleAttribute: 'label',
                    modifyQueryUsing: fn (Builder $q) => $q->where('purpose', 'marketing'),
                )
                ->searchable()
                ->preload()
                ->required(),

            TextInput::make('subject')
                ->required()
                ->maxLength(255),

            RichEditor::make('body_html')
                ->label('Body')
                ->columnSpanFull()
                ->required(),

            // Read-only meta (instead of Placeholder)
            TextInput::make('status')
                ->disabled()
                ->dehydrated(false),

            TextInput::make('sent_count')
                ->numeric()
                ->disabled()
                ->dehydrated(false),
        ]);
    }
}
