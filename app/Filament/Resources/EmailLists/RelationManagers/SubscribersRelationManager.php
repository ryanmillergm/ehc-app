<?php

namespace App\Filament\Resources\EmailLists\RelationManagers;

use App\Models\EmailSubscriber;
use App\Support\EmailCanonicalizer;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class SubscribersRelationManager extends RelationManager
{
    protected static string $relationship = 'subscribers';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('email')->searchable(),
                TextColumn::make('name')->label('Name')->toggleable(),

                IconColumn::make('pivot.unsubscribed_at')
                    ->label('Active')
                    ->boolean()
                    ->getStateUsing(fn (EmailSubscriber $record) => is_null($record->pivot?->unsubscribed_at)),

                TextColumn::make('pivot.subscribed_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('pivot.unsubscribed_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                Action::make('addSubscriber')
                    ->label('Add subscriber')
                    ->schema([
                        TextInput::make('email')->email()->required(),
                        TextInput::make('first_name'),
                        TextInput::make('last_name'),
                    ])
                    ->action(function (array $data): void {
                        $list = $this->getOwnerRecord();

                        $email = trim(strtolower($data['email']));
                        $canonical = EmailCanonicalizer::canonicalize($email) ?? $email;

                        $subscriber = EmailSubscriber::query()
                            ->where('email_canonical', $canonical)
                            ->orWhere('email', $canonical)
                            ->first();

                        if (! $subscriber) {
                            $subscriber = EmailSubscriber::create([
                                'email' => $canonical,
                                'first_name' => $data['first_name'] ?? null,
                                'last_name' => $data['last_name'] ?? null,
                                'unsubscribe_token' => Str::random(64),
                                'subscribed_at' => now(),
                                'preferences' => [],
                            ]);
                        } else {
                            // keep global opted-in for marketing + fill names if provided
                            $subscriber->update([
                                'first_name' => $data['first_name'] ?: $subscriber->first_name,
                                'last_name'  => $data['last_name']  ?: $subscriber->last_name,
                                'unsubscribed_at' => null,
                                'subscribed_at' => $subscriber->subscribed_at ?? now(),
                            ]);
                        }

                        // Attach/resubscribe on this list (pivot controls list opt-out)
                        $list->subscribers()->syncWithoutDetaching([
                            $subscriber->id => [
                                'subscribed_at' => now(),
                                'unsubscribed_at' => null,
                            ],
                        ]);

                        Notification::make()
                            ->title('Subscriber added to list')
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
                Action::make('unsubscribe')
                    ->label('Unsubscribe')
                    ->visible(fn (EmailSubscriber $record) => is_null($record->pivot?->unsubscribed_at))
                    ->requiresConfirmation()
                    ->action(function (EmailSubscriber $record): void {
                        $this->getOwnerRecord()
                            ->subscribers()
                            ->updateExistingPivot($record->id, ['unsubscribed_at' => now()]);
                    }),

                Action::make('resubscribe')
                    ->label('Resubscribe')
                    ->visible(fn (EmailSubscriber $record) => ! is_null($record->pivot?->unsubscribed_at))
                    ->action(function (EmailSubscriber $record): void {
                        $this->getOwnerRecord()
                            ->subscribers()
                            ->updateExistingPivot($record->id, [
                                'unsubscribed_at' => null,
                                'subscribed_at' => $record->pivot?->subscribed_at ?? now(),
                            ]);
                    }),
                \Filament\Actions\DetachAction::make(),
            ]);
    }
}
