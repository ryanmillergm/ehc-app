<?php

namespace App\Filament\Resources\PageTranslationResource\RelationManagers;

use App\Models\SeoMeta;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SeoMetaRelationManager extends RelationManager
{
    protected static string $relationship = 'seoMeta';

    protected static ?string $title = 'SEO Meta (Canonical)';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Hidden::make('target_key')
                ->default(''),
            Hidden::make('language_id')
                ->default(fn (): int => (int) $this->getOwnerRecord()->language_id),
            TextInput::make('seo_title')
                ->maxLength(255),
            Textarea::make('seo_description')
                ->rows(3)
                ->columnSpanFull(),
            TextInput::make('seo_og_image')
                ->label('SEO OG Image URL')
                ->maxLength(500),
            Toggle::make('is_active')
                ->default(true)
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->where('target_key', '')
                ->where('language_id', $this->getOwnerRecord()->language_id))
            ->columns([
                TextColumn::make('language.title')
                    ->label('Language'),
                TextColumn::make('target_key')
                    ->label('Target')
                    ->formatStateUsing(fn (?string $state): string => blank($state) ? 'canonical' : $state),
                TextColumn::make('seo_title')
                    ->limit(60),
                IconColumn::make('is_active')
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Create Canonical SEO')
                    ->visible(fn (): bool => ! $this->canonicalSeoExists())
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['target_key'] = '';
                        $data['language_id'] = (int) $this->getOwnerRecord()->language_id;
                        $data['is_active'] = (bool) ($data['is_active'] ?? true);

                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['target_key'] = '';
                        $data['language_id'] = (int) $this->getOwnerRecord()->language_id;

                        return $data;
                    }),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    private function canonicalSeoExists(): bool
    {
        return SeoMeta::query()
            ->where('seoable_type', $this->getOwnerRecord()->getMorphClass())
            ->where('seoable_id', $this->getOwnerRecord()->getKey())
            ->where('target_key', '')
            ->where('language_id', $this->getOwnerRecord()->language_id)
            ->exists();
    }
}
