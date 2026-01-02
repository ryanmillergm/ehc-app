<?php

namespace App\Filament\Resources;

use App\Filament\Navigation\NavigationGroup;
use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DateTimePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\ViewAction;
use Filament\Actions\Action;
use App\Filament\Resources\TransactionResource\Pages\ListTransactions;
use App\Filament\Resources\TransactionResource\Pages\CreateTransaction;
use App\Filament\Resources\TransactionResource\Pages\EditTransaction;
use App\Filament\Resources\TransactionResource\Pages;
use App\Filament\Resources\TransactionResource\RelationManagers;
use App\Models\Transaction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-receipt-percent';
    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Donations;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('amount_cents')
                    ->label('Amount (cents)')
                    ->numeric()
                    ->required(),
                TextInput::make('currency')
                    ->default('usd'),
                TextInput::make('type'),
                TextInput::make('status'),
                TextInput::make('payment_intent_id')->disabled(),
                TextInput::make('charge_id')->disabled(),
                TextInput::make('customer_id')->disabled(),
                DateTimePicker::make('paid_at'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('payer_name')
                    ->label('Donor')
                    ->searchable(),
                TextColumn::make('amount_cents')
                    ->label('Amount')
                    ->formatStateUsing(fn ($state) => '$' . number_format($state / 100, 2)),
                TextColumn::make('type')->badge(),
                TextColumn::make('status')->badge(),
                TextColumn::make('paid_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending'   => 'Pending',
                        'succeeded' => 'Succeeded',
                        'failed'    => 'Failed',
                        'refunded'  => 'Refunded',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('refund')
                    ->label('Refund')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Transaction $record) =>
                        $record->status === 'succeeded' && $record->charge_id
                    )
                    ->action(function (Transaction $record, array $data): void {
                        /** @var StripeService $stripe */
                        $stripe = app(StripeService::class);

                        $amountCents = $data['amount_cents'] ?? null;
                        $stripe->refund($record, $amountCents ? (int) $amountCents : null);
                    })
                    ->schema([
                        TextInput::make('amount_cents')
                            ->label('Amount (cents; leave blank for full)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(fn (Transaction $record) => $record->amount_cents),
                    ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'     => ListTransactions::route('/'),
            'create'    => CreateTransaction::route('/create'),
            'edit'      => EditTransaction::route('/{record}/edit'),
        ];
    }
}
