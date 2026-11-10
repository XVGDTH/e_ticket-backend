<?php

namespace App\Filament\Resources\Ecritures\Pages;

use App\Filament\Pages\SaisieEcriture;
use App\Filament\Resources\Ecritures\EcritureResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListEcritures extends ListRecords
{
    protected static string $resource = EcritureResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('nouvelle')
                ->label('Nouvelle écriture')
                ->icon('heroicon-o-plus')
                ->url(SaisieEcriture::getUrl()),
        ];
    }

    public function getTabs(): array
    {
        return [
            'toutes' => Tab::make('Toutes'),

            'brouillons' => Tab::make('Brouillons')
                ->icon('heroicon-o-pencil')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('statut', 'brouillon')),

            'comptabilisees' => Tab::make('Comptabilisées')
                ->icon('heroicon-o-check-badge')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('statut', 'comptabilise')),

            'factures' => Tab::make('Factures')
                ->icon('heroicon-o-receipt-percent')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('est_facture', true)),

            'automatiques' => Tab::make('Automatiques')
                ->icon('heroicon-o-bolt')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('origine', 'automatique')),
        ];
    }
}