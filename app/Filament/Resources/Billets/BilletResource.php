<?php

namespace App\Filament\Resources\Billets;

use App\Enums\BilletStatut;
use App\Filament\Concerns\ScopedToCompagnie;
use App\Filament\Resources\Billets\Pages;
use App\Models\Billet;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class BilletResource extends Resource
{
    use ScopedToCompagnie;

    protected static ?string $model = Billet::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-ticket';
    protected static ?string $navigationLabel = 'Billets';
    protected static string|UnitEnum|null $navigationGroup = 'Activité';

    protected static function droits(): array
    {
        return [
            'super_admin' => ['voir', 'supprimer', 'statut'],
            'admin_compagnie' => ['voir', 'statut'],
            'agent_gare' => ['voir'],
        ];
    }

    // Le billet suit sa gare : voir Billet::scopeVisiblesPar() pour la règle complète.
    protected static function scopeToCompagnie(Builder $query, ?int $compagnieId): Builder
    {
        return $compagnieId
            ? $query->whereHas('gare', fn ($g) => $g->where('compagnie_id', $compagnieId))
            : $query->whereRaw('1 = 0');
    }

    protected static function scopeToGare(Builder $query, ?int $gareId): Builder
    {
        return $gareId ? $query->where('billets.gare_id', $gareId) : $query->whereRaw('1 = 0');
    }

    protected static function scopeToVille(Builder $query, int $villeId): Builder
    {
        return $query->whereHas('gare', fn ($g) => $g->where('ville_id', $villeId));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference')->label('Référence')->searchable(),
                Tables\Columns\TextColumn::make('statut')->label('Statut')->badge(),
                Tables\Columns\TextColumn::make('user.prenom')
                    ->label('Client')
                    ->formatStateUsing(fn ($state, $record) => trim(($record->user?->prenom ?? '') . ' ' . ($record->user?->nom ?? ''))),
                Tables\Columns\TextColumn::make('gare.quartier')->label('Gare de départ'),
                Tables\Columns\TextColumn::make('gare.compagnie.nom')
                    ->label('Compagnie')
                    ->visible(fn () => ! auth()->user()->isAgentGare()),
                Tables\Columns\TextColumn::make('date_voyage')->label('Date du voyage')->date('d/m/Y')->sortable(),
                Tables\Columns\TextColumn::make('heure')->label('Heure'),
                Tables\Columns\TextColumn::make('montant_transport')->label('Montant')->money('XOF'),
            ])
            ->filters([
                SelectFilter::make('statut')
                    ->label('Statut')
                    ->options(collect(BilletStatut::cases())->mapWithKeys(fn (BilletStatut $s) => [$s->value => $s->getLabel()])->all()),
            ])
            ->recordActions([
                static::actionStatut('annuler', 'Annuler', BilletStatut::Annule, 'danger', 'heroicon-o-x-circle'),
                static::actionStatut('rembourser', 'Rembourser', BilletStatut::Rembourse, 'warning', 'heroicon-o-arrow-uturn-left'),
            ])
            ->defaultSort('date_voyage', 'desc');
    }

    protected static function actionStatut(string $nom, string $libelle, BilletStatut $statut, string $couleur, string $icone): Actions\Action
    {
        return Actions\Action::make($nom)
            ->label($libelle)
            ->color($couleur)
            ->icon($icone)
            ->requiresConfirmation()
            ->schema([
                Forms\Components\Textarea::make('motif')
                    ->label('Motif (obligatoire)')
                    ->required()
                    ->minLength(5)
                    ->maxLength(255),
            ])
            ->visible(fn (Billet $record): bool => static::peut('statut') && $record->statut === BilletStatut::Valide)
            ->action(function (Billet $record, array $data) use ($statut): void {
                try {
                    $record->changerStatut($statut, auth()->user(), $data['motif'], 'panel');

                    Notification::make()->title('Billet mis à jour')->success()->send();
                } catch (AuthorizationException | \DomainException $e) {
                    Notification::make()->title('Opération refusée')->body($e->getMessage())->danger()->send();
                }
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBillets::route('/'),
        ];
    }
}