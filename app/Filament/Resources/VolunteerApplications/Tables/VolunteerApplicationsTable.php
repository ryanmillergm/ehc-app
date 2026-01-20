<?php

namespace App\Filament\Resources\VolunteerApplications\Tables;

use App\Models\VolunteerApplication;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class VolunteerApplicationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.full_name')
                    ->label('Volunteer')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('need.title')
                    ->label('Need')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Submitted')
                    ->dateTime()
                    ->sortable()
                    ->since(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        VolunteerApplication::STATUS_SUBMITTED => 'Submitted',
                        VolunteerApplication::STATUS_REVIEWING => 'Reviewing',
                        VolunteerApplication::STATUS_ACCEPTED  => 'Accepted',
                        VolunteerApplication::STATUS_REJECTED  => 'Rejected',
                        VolunteerApplication::STATUS_WITHDRAWN => 'Withdrawn',
                    ]),

                SelectFilter::make('volunteer_need_id')
                    ->label('Need')
                    ->relationship('need', 'title'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
