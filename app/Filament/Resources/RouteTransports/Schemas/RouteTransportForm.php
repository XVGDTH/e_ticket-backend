<?php

namespace App\Filament\Resources\RouteTransports\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class RouteTransportForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('compagnie_id')
                    ->required()
                    ->numeric(),
                // TextInput::make('gare_depart_id')
                //     ->required()
                //     ->numeric(),
                Forms\Components\Select::make('gare_depart_id')
                ->label('Gare de départ')
                ->relationship(
                    'gareDepart',
                    'quartier',
                    fn (Builder $query) => $user->isSuperAdmin()
                        ? $query
                        : $query->where('compagnie_id', $user->compagnieId())
                )
                ->required()
                ->searchable()
                ->rules([
                    fn () => function (string $attribute, $value, \Closure $fail) use ($user) {
                        if ($user->isSuperAdmin()) {
                            return;
                        }

                        $ok = \App\Models\Gare::whereKey($value)
                            ->where('compagnie_id', $user->compagnieId())
                            ->exists();

                        if (! $ok) {
                            $fail('Cette gare n\'appartient pas à votre compagnie.');
                        }
                    },
                ]),
                TextInput::make('ville_arrivee_id')
                    ->required()
                    ->numeric(),
                TextInput::make('prix')
                    ->required()
                    ->numeric(),
                TextInput::make('duree_max')
                    ->default(null),
                Toggle::make('actif')
                    ->required(),
            ]);
    }
}
