<?php

namespace App\Filament\Resources\AvisGares;

use App\Filament\Resources\AvisGares\Pages;
use App\Models\AvisGare;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;
use App\Filament\Concerns\ScopedToCompagnie;


class AvisGareResource extends Resource
{

  use ScopedToCompagnie;
    protected static ?string $model = AvisGare::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-star';
    protected static ?string $navigationLabel = 'Avis voyageurs';
    protected static string|UnitEnum|null $navigationGroup = 'Activité';

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('billet.reference')->label('Billet'),
            Tables\Columns\TextColumn::make('gare.quartier')->label('Gare'),
            Tables\Columns\TextColumn::make('gare.compagnie.nom')->label('Compagnie'),
            Tables\Columns\TextColumn::make('commentaire')->limit(50),
            Tables\Columns\TextColumn::make('created_at')->label('Date')->date('d/m/Y'),
        ])->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAvisGares::route('/'),
        ];
    }


        protected static function droits(): array
    {
        return [
            'super_admin' => ['voir'],
            'admin_compagnie' => ['voir'],
            'agent_gare' => ['voir'],
        ];
    }

    protected static function scopeToCompagnie(Builder $query, ?int $compagnieId): Builder
    {
        return $compagnieId
            ? $query->whereHas('gare', fn ($q) => $q->where('compagnie_id', $compagnieId))
            : $query->whereRaw('1 = 0');
    }

    protected static function scopeToGare(Builder $query, ?int $gareId): Builder
    {
        return $gareId
            ? $query->whereHas('gare', fn ($q) => $q->whereKey($gareId))
            : $query->whereRaw('1 = 0');
    }
        protected static function scopeToVille(Builder $query, int $villeId): Builder
    {
        return $query->whereHas('gare', fn ($g) => $g->where('ville_id', $villeId));
    }
}