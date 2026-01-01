<?php

namespace App\Filament\Org\Pages\Tenancy;

use Filament\Schemas\Schema;
use App\Models\Team;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Tenancy\RegisterTenant;

class RegisterTeam extends RegisterTenant
{
    public static function getLabel(): string
    {
        return 'Register team';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name'),
                TextInput::make('slug'),
                // ...
            ]);
    }

    protected function handleRegistration(array $data): Team
    {
        $data['user_id'] = auth()->user()->id;

        $team = Team::create($data);

        $team->members()->attach(auth()->user());

        return $team;
    }
}
