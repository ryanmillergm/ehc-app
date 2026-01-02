<?php

namespace App\Filament\Resources\EmailLists\Schemas;

use App\Models\EmailList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class EmailListForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('label')
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(function (string $state, callable $set, ?EmailList $record) {
                    // On create only, auto-fill key if it's empty.
                    if ($record) {
                        return;
                    }

                    $set('key', Str::slug($state));
                }),

            TextInput::make('key')
                ->required()
                ->maxLength(255)
                ->unique(table: 'email_lists', column: 'key', ignoreRecord: true)
                ->helperText('Internal identifier (unique). Example: newsletter'),

            Textarea::make('description')
                ->columnSpanFull(),

            Select::make('purpose')
                ->required()
                ->default('marketing')
                ->options([
                    'marketing' => 'Marketing',
                    'transactional' => 'Transactional',
                ])
                ->disabled(fn (?EmailList $record) => $record?->campaigns()->exists() ?? false)
                ->helperText('Purpose can’t be changed after campaigns exist.'),

            Toggle::make('is_default')
                ->default(false),

            Toggle::make('is_opt_outable')
                ->default(true),
        ]);
    }
}
