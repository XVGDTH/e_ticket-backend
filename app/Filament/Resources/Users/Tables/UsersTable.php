<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use App\Support\AppColors;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('prenom')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->color(AppColors::TEXT_DARK),


                TextColumn::make('nom')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->color(AppColors::TEXT_DARK),


                TextColumn::make('telephone')
                    ->searchable()
                    ->searchable()
                    ->icon('heroicon-o-phone')
                    ->iconColor('primary')
                    ->copyable()
                    ->copyMessage('Numéro copié !'),


                IconColumn::make('telephone_verifie')
                    ->label('Vérifié')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('primary')
                    ->falseColor('danger'),

            IconColumn::make('actif')
                    ->label('Actif')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('primary')
                    ->falseColor('danger'),


                TextColumn::make('created_at')
                    // ->dateTime()
                    // ->sortable()
                    // ->toggleable(isToggledHiddenByDefault: true),
                    ->label('Créé le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),


                TextColumn::make('updated_at')
                    // ->dateTime()
                    // ->sortable()
                    // ->toggleable(isToggledHiddenByDefault: true),
                    ->label('Modifié le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()
                ->color('primary')
                    ->icon('heroicon-o-pencil-square'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                   
                        ->color('danger'),

                ]),
            ])
            ->striped()
            ->defaultSort('nom', 'asc');
    }
}
