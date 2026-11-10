<?php

namespace App\Filament\Resources\ClientsFideles;

use App\Models\PreferenceClient;
use App\Services\Marketing\Audience;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class PreferenceClientResource extends Resource
{
    protected static ?string $model = PreferenceClient::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-heart';

    protected static ?string $navigationLabel = 'Clients fidèles';

    protected static ?string $modelLabel = 'client';

    protected static ?string $pluralModelLabel = 'clients fidèles';

    protected static string|UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->isSuperAdmin();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user', 'compagnie', 'gare']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('score', 'desc')
            ->columns([
                TextColumn::make('client')
                    ->label('Client')
                    ->getStateUsing(fn (PreferenceClient $r) => trim(($r->user?->prenom ?? '').' '.($r->user?->nom ?? '')) ?: '—')
                    ->description(fn (PreferenceClient $r) => $r->user?->telephone)
                    ->searchable(query: fn (Builder $q, string $s) => $q->whereHas('user', fn ($u) => $u
                        ->where('nom', 'like', "%{$s}%")->orWhere('prenom', 'like', "%{$s}%")->orWhere('telephone', 'like', "%{$s}%"))),
                TextColumn::make('compagnie.nom')->label('Compagnie')->badge()->color('info'),
                TextColumn::make('gare.quartier')->label('Gare')->placeholder('Toutes'),
                TextColumn::make('score')
                    ->label('Score')
                    ->badge()
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 0).' / 100')
                    ->color(fn ($state) => $state >= 60 ? 'success' : ($state >= 35 ? 'warning' : 'gray'))
                    ->sortable(),
                TextColumn::make('segment')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => Audience::SEGMENTS[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'fidele' => 'success', 'regulier' => 'info', 'occasionnel' => 'warning', default => 'gray',
                    }),
                TextColumn::make('nb_billets')->label('Billets')->sortable(),
                TextColumn::make('montant')->label('Dépensé')->numeric(0, ',', ' ')->suffix(' FCFA')->alignEnd()->sortable(),
                TextColumn::make('dernier_achat')->label('Dernier achat')->date('d/m/Y')->sortable(),
                IconColumn::make('preferee')
                    ->label('Préférée')
                    ->getStateUsing(fn (PreferenceClient $r) => $r->rang === 1)
                    ->boolean()
                    ->trueIcon('heroicon-s-star')->trueColor('warning')
                    ->falseIcon('heroicon-o-minus')->falseColor('gray'),
                IconColumn::make('user.marketing_whatsapp')->label('Accepte les offres')->boolean(),
            ])
            ->filters([
                SelectFilter::make('niveau')
                    ->label('Niveau')
                    ->options(['compagnie' => 'Par compagnie', 'gare' => 'Par gare'])
                    ->default('compagnie')
                    ->query(fn (Builder $q, array $data) => ($data['value'] ?? 'compagnie') === 'gare'
                        ? $q->where('gare_id', '>', 0)
                        : $q->where('gare_id', 0)),
                SelectFilter::make('compagnie_id')->label('Compagnie')->relationship('compagnie', 'nom'),
                SelectFilter::make('segment')->label('Type de client')->options(Audience::SEGMENTS),
                TernaryFilter::make('consentement')
                    ->label('Accepte les offres')
                    ->queries(
                        true: fn (Builder $q) => $q->whereHas('user', fn ($u) => $u->where('marketing_whatsapp', true)),
                        false: fn (Builder $q) => $q->whereHas('user', fn ($u) => $u->where('marketing_whatsapp', false)),
                        blank: fn (Builder $q) => $q,
                    ),
            ])
            ->striped();
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListClientsFideles::route('/')];
    }
}
