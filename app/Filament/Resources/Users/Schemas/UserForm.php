<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('prenom')
                    ->default(null),
                TextInput::make('nom')
                    ->default(null),
                TextInput::make('telephone')
                    ->tel()
                    ->default(null),
                Toggle::make('telephone_verifie')
                    ->required(),
                TextInput::make('password')
                    ->password()
                    ->required(),
            ]);
    }
}
