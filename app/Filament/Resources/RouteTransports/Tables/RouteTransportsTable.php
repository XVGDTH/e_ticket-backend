<?php

namespace App\Filament\Resources\RouteTransports\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RouteTransportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('compagnie_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('gare_depart_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('ville_arrivee_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('prix')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('duree_max')
                    ->searchable(),
                IconColumn::make('actif')
                    ->boolean(),
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
