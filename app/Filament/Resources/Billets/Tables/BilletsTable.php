<?php

namespace App\Filament\Resources\Billets\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BilletsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference')
                    ->searchable(),
                TextColumn::make('user_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('route_transport_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('gare_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('date_voyage')
                    ->date()
                    ->sortable(),
                TextColumn::make('heure')
                    ->searchable(),
                TextColumn::make('travelers_count')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('montant_transport')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('taxe')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('operateur_paiement')
                    ->searchable(),
                TextColumn::make('date_paiement')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
