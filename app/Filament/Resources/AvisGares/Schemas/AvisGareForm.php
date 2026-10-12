<?php

namespace App\Filament\Resources\AvisGares\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class AvisGareForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('billet_id')
                    ->relationship('billet', 'id')
                    ->required(),
                Select::make('gare_id')
                    ->relationship('gare', 'id')
                    ->required(),
                Textarea::make('scores')
                    ->required()
                    ->columnSpanFull(),
                Textarea::make('commentaire')
                    ->default(null)
                    ->columnSpanFull(),
            ]);
    }
}
