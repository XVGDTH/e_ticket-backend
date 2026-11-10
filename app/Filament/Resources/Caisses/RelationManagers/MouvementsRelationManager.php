<?php

namespace App\Filament\Resources\Caisses\RelationManagers;

use App\Models\MouvementCaisse;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MouvementsRelationManager extends RelationManager
{
    protected static string $relationship = 'mouvements';

    protected static ?string $title = 'Mouvements de la caisse';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['billet', 'user'])->orderByDesc('created_at'))
            ->columns([
                TextColumn::make('created_at')->label('Heure')->dateTime('d/m H:i'),
                TextColumn::make('type')->label('Opération')->badge()->formatStateUsing(fn ($s) => MouvementCaisse::TYPES[$s] ?? $s)
                    ->color(fn ($s) => match ($s) {
                        'vente', 'entree' => 'success', 'sortie' => 'warning', default => 'danger'
                    }),
                TextColumn::make('billet.reference')->label('Billet')->placeholder('—')->fontFamily('mono'),
                TextColumn::make('mode')->label('Mode')->formatStateUsing(fn ($s) => config('guichet.modes')[$s] ?? $s),
                TextColumn::make('montant')->label('Montant')->numeric(0, ',', ' ')->suffix(' FCFA')->alignEnd()->weight('bold')
                    ->color(fn ($state) => $state < 0 ? 'danger' : null),
                TextColumn::make('motif')->label('Motif')->placeholder('—')->limit(40),
                TextColumn::make('user.prenom')->label('Par')->formatStateUsing(fn ($state, MouvementCaisse $r) => trim(($r->user?->prenom ?? '').' '.($r->user?->nom ?? ''))),
            ])
            ->striped();
    }
}
