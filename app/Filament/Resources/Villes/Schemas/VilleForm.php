<?php

namespace App\Filament\Resources\Villes\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class VilleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nom')
                    ->required(),
                Textarea::make('aliases')
                    ->default(null)
                    ->columnSpanFull(),
            ]);
    }
}
