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
                TextInput::make('places_par_depart')
                    ->label('Places par car')
                    ->numeric()->minValue(1)->maxValue(200)->required()->default(60)
                    ->disabled(fn () => ! self::peutRegler())
                    ->helperText('Nombre réel de places de vos cars, par départ.'),
                TextInput::make('quota_app_pourcent')
                    ->label('Quota de l\'application mobile')
                    ->numeric()->minValue(0)->maxValue(100)->suffix('%')->required()->default(100)
                    ->disabled(fn () => ! self::peutRegler())
                    ->helperText('Part des places de chaque départ vendue via l\'application.'),
                TextInput::make('validite_jours')
                    ->label('Durée de validité des billets')
                    ->numeric()->minValue(1)->maxValue(90)->suffix('jours')->required()->default(12)
                    ->disabled(fn () => ! self::peutRegler())
                    ->helperText('Compte à partir de l\'achat. Ne concerne que les billets achetés ensuite.'),
                Toggle::make('actif')
                    ->required(),
            ]);
    }

    /** Réglages de toute la compagnie : super admin, ou admin non limité à une ville. */
    private static function peutRegler(): bool
    {
        $user = auth()->user();

        return $user->isSuperAdmin() || ($user->isAdminCompagnie() && ! $user->villeId());
    }
}
