<?php

namespace App\Filament\Resources\Compagnies\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CompagnieForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nom')
                    ->required(),
                TextInput::make('slug')
                    ->required(),
                TextInput::make('logo_url')
                    ->url()
                    ->default(null),
                Toggle::make('actif')
                    ->required(),
            ]);
    }
}
