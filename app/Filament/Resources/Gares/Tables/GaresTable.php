<?php

namespace App\Filament\Resources\Gares\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class GaresTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('quartier')
                    ->label('Gare')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('compagnie.nom')
                    ->label('Compagnie')
                    ->searchable()
                    ->sortable()
                    // ->visible(fn () => auth()->user()->auth()->user()->isSuperAdmin()),
                    ->visible(fn () => auth()->user()->isSuperAdmin()),
                TextColumn::make('ville.nom')
                    ->label('Ville')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('telephone')
                    ->label('Téléphone')
                    ->searchable(),
                TextColumn::make('latitude')
                    ->numeric()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('longitude')
                    ->numeric()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Créée le')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('ville_id')
                    ->label('Ville')
                    ->relationship('ville', 'nom'),
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