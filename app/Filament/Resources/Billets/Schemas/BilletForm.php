<?php

namespace App\Filament\Resources\Billets\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class BilletForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('reference')
                    ->required(),
                TextInput::make('user_id')
                    ->required()
                    ->numeric(),
                TextInput::make('route_transport_id')
                    ->required()
                    ->numeric(),
                TextInput::make('gare_id')
                    ->required()
                    ->numeric(),
                DatePicker::make('date_voyage')
                    ->required(),
                TextInput::make('heure')
                    ->required(),
                TextInput::make('travelers_count')
                    ->required()
                    ->numeric(),
                TextInput::make('montant_transport')
                    ->required()
                    ->numeric(),
                TextInput::make('taxe')
                    ->required()
                    ->numeric(),
                TextInput::make('operateur_paiement')
                    ->required(),
                Textarea::make('voyageurs')
                    ->required()
                    ->columnSpanFull(),
                DateTimePicker::make('date_paiement')
                    ->required(),
            ]);
    }
}
