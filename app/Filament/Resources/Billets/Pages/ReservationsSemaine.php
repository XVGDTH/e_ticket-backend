<?php

namespace App\Filament\Resources\Billets\Pages;

use App\Enums\BilletStatut;
use App\Filament\Resources\Billets\BilletResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ReservationsSemaine extends ListRecords
{
    protected static string $resource = BilletResource::class;

    protected static ?string $title = 'Réservations';

    protected function reservees(Builder $query): Builder
    {
        return $query
            ->whereBetween('billets.date_voyage', [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()])
            ->whereIn('billets.statut', [BilletStatut::Valide, BilletStatut::Utilise]);
    }

    protected function enAttente(Builder $query): Builder
    {
        return $query->where('billets.statut', BilletStatut::EnAttentePaiement);
    }

    public function getTabs(): array
    {
        return [
            'reservees' => Tab::make('Réservés cette semaine')
                ->badge($this->reservees(BilletResource::getEloquentQuery())->count())
                ->modifyQueryUsing(fn (Builder $query) => $this->reservees($query)),
            'en_attente' => Tab::make('En attente de paiement')
                ->badge($this->enAttente(BilletResource::getEloquentQuery())->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $this->enAttente($query)),
        ];
    }
}
