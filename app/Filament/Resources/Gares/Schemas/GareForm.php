<?php

namespace App\Filament\Resources\Gares\Schemas;

use App\Models\Ville;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class GareForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Visible seulement pour le super admin : sinon la compagnie est imposée par le serveur.
                Select::make('compagnie_id')
                    ->label('Compagnie')
                    ->relationship('compagnie', 'nom')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->visible(fn () => auth()->user()->isSuperAdmin()),
                // Un administrateur de ville ne choisit pas la ville : elle est imposée par le serveur.
                // Select::make('ville_id')
                //     ->label('Ville')
                //     ->relationship('ville', 'nom')
                //     ->searchable()
                //     ->preload()
                //     ->required()
                //     ->visible(fn () => ! auth()->user()->villeId()),
                Select::make('ville_id')
                    ->label('Ville')
                    ->relationship('ville', 'nom')
                    ->getOptionLabelFromRecordUsing(fn (Ville $ville) => $ville->etranger
                        ? "{$ville->nom} ({$ville->pays}) — étranger"
                        : $ville->nom)
                    ->searchable()
                    ->preload()
                    ->required()
                    ->visible(fn () => ! auth()->user()->villeId()),
                TextInput::make('quartier')
                    ->label('Quartier / nom de la gare')
                    ->required()
                    ->maxLength(255),
                // TextInput::make('latitude')
                //     ->numeric()
                //     ->minValue(-90)
                //     ->maxValue(90),
                // TextInput::make('longitude')
                //     ->numeric()
                //     ->minValue(-180)
                //     ->maxValue(180),
                TextInput::make('latitude')
                    ->label('Latitude')
                    ->numeric()->step('any')->minValue(-90)->maxValue(90)
                    ->helperText('Au Burkina Faso, autour de 12 (ex. 12.3714). Sur Google Maps : clic droit sur la gare, puis cliquer sur les coordonnées pour les copier.'),
                TextInput::make('longitude')
                    ->label('Longitude')
                    ->numeric()->step('any')->minValue(-180)->maxValue(180)
                    ->helperText('Au Burkina Faso, négative et proche de -1 (ex. -1.5197).'),
                TextInput::make('telephone')
                    ->label('Téléphone')
                    ->tel()
                    ->maxLength(30),
                Textarea::make('adresse')
                    ->columnSpanFull(),
            ]);
    }
}
