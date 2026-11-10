<?php

namespace App\Filament\Resources\Billets;

use App\Enums\BilletStatut;
use App\Filament\Concerns\ScopedToCompagnie;
use App\Filament\Resources\Gares\GareResource;
use App\Models\Billet;
use App\Models\RouteTransport;
use App\Models\Ville;
use Filament\Actions;
use Filament\Forms;
use Filament\Navigation\NavigationItem;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
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
                    ->formatStateUsing(fn ($state, $record) => trim(($record->user?->prenom ?? '').' '.($record->user?->nom ?? ''))),
                Tables\Columns\TextColumn::make('gare.quartier')->label('Gare de départ'),
                // nouvelle routes pour le dashboard billet
                Tables\Columns\TextColumn::make('route.villeArrivee.nom')->label('Destination'), // ADAPTER : nom de la relation
                Tables\Columns\TextColumn::make('travelers_count')->label('Voyageurs'),

                Tables\Columns\TextColumn::make('gare.compagnie.nom')
                    ->label('Compagnie')
                    ->visible(fn () => ! auth()->user()->isAgentGare()),
                Tables\Columns\TextColumn::make('date_voyage')->label('Date du voyage')->date('d/m/Y')->sortable(),
                Tables\Columns\TextColumn::make('heure')->label('Heure'),
                Tables\Columns\TextColumn::make('montant_transport')->label('Montant')->money('XOF'),
            ])
            // ->filters([
            //     SelectFilter::make('statut')
            //         ->label('Statut')
            //         ->options(collect(BilletStatut::cases())->mapWithKeys(fn (BilletStatut $s) => [$s->value => $s->getLabel()])->all()),
            // ])
            ->filters([
                SelectFilter::make('statut')
                    ->label('Statut')
                    ->options(collect(BilletStatut::cases())->mapWithKeys(fn (BilletStatut $s) => [$s->value => $s->getLabel()])->all()),

                SelectFilter::make('heure')
                    ->label('Heure')
                    ->options(fn () => static::getEloquentQuery()->reorder()
                        ->selectRaw('DISTINCT LEFT(billets.heure, 5) as hhmm')
                        ->orderBy('hhmm')->pluck('hhmm', 'hhmm')->all())
                    ->query(fn (Builder $query, array $data) => filled($data['value'] ?? null)
                        ? $query->whereRaw('LEFT(billets.heure, 5) = ?', [$data['value']])
                        : $query),

                SelectFilter::make('gare_id')
                    ->label('Zone de départ')
                    ->options(fn () => GareResource::getEloquentQuery()
                        ->pluck('quartier', 'id')->all()),

                SelectFilter::make('destination')
                    ->label('Zone d\'arrivée')
                    ->options(fn () => Ville::orderBy('nom')->pluck('nom', 'id')->all())
                    ->query(fn (Builder $query, array $data) => filled($data['value'] ?? null)
                        ? $query->whereHas('route', fn ($route) => $route->where('ville_arrivee_id', $data['value']))
                        : $query),
            ])
            // ->recordActions([
            //     static::actionStatut('annuler', 'Annuler', BilletStatut::Annule, 'danger', 'heroicon-o-x-circle'),
            //     static::actionStatut('rembourser', 'Rembourser', BilletStatut::Rembourse, 'warning', 'heroicon-o-arrow-uturn-left'),
            // ])
            ->groups([
                Group::make('heure')->label('Heure'),
                Group::make('gare.quartier')->label('Zone de départ'),
                Group::make('arrivee')
                    ->label('Zone d\'arrivée')
                    ->getKeyFromRecordUsing(fn (Billet $record) => (string) $record->route?->ville_arrivee_id)
                    ->getTitleFromRecordUsing(fn (Billet $record) => $record->route?->villeArrivee?->nom ?? '—')
                    ->orderQueryUsing(fn (Builder $query, string $direction) => $query->orderBy(
                        RouteTransport::query()
                            ->select('ville_arrivee_id')
                            ->whereColumn((new RouteTransport)->getQualifiedKeyName(), 'billets.route_transport_id'),
                        $direction
                    ))
                    ->scopeQueryByKeyUsing(fn (Builder $query, string $key) => $query
                        ->whereHas('route', fn ($route) => $route->where('ville_arrivee_id', $key))),
            ])
            ->defaultSort('date_voyage', 'desc')

            // fin du nouveau code
            // ->recordActions([
            //     static::actionStatut('annuler', 'Annuler', BilletStatut::Annule, 'danger', 'heroicon-o-x-circle'),
            //     static::actionStatut('rembourser', 'Rembourser', BilletStatut::Rembourse, 'warning', 'heroicon-o-arrow-uturn-left'),
            // ])

            ->recordActions([
                static::actionStatut('annuler', 'Annuler', BilletStatut::Annule, 'danger', 'heroicon-o-x-circle'),
                static::actionStatut('rembourser', 'Rembourser', BilletStatut::Rembourse, 'warning', 'heroicon-o-arrow-uturn-left'),
                Actions\Action::make('embarquer')
                    ->label('Embarquer')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Billet $record): bool => auth()->user()->isAgentGare() && $record->statut === BilletStatut::Valide)
                    ->action(function (Billet $record): void {
                        try {
                            $record->changerStatut(BilletStatut::Utilise, auth()->user(), null, 'panel');

                            Notification::make()->title('Billet embarqué')->success()->send();
                        } catch (AuthorizationException|\DomainException $e) {
                            Notification::make()->title('Opération refusée')->body($e->getMessage())->danger()->send();
                        }
                    }),
            ]);
        Actions\Action::make('corriger')
            ->label('Annuler l\'embarquement')
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('gray')
            ->requiresConfirmation()
            ->modalDescription('À utiliser seulement si ce voyageur a été embarqué par erreur : son billet redevient valable.')
            ->visible(fn (Billet $record): bool => auth()->user()->isAgentGare()
                && $record->statut === BilletStatut::Utilise
                && (bool) $record->updated_at?->isToday())
            ->action(function (Billet $record): void {
                try {
                    $record->changerStatut(BilletStatut::Valide, auth()->user(), null, 'panel');

                    Notification::make()->title('Embarquement annulé')->success()->send();
                } catch (AuthorizationException|\DomainException $e) {
                    Notification::make()->title('Opération refusée')->body($e->getMessage())->danger()->send();
                }
            })
            ->modifyQueryUsing(fn (Builder $query, $livewire) => $livewire instanceof Pages\ListBillets
                ? $livewire->restreindre($query)
                : $query)
            ->header(fn ($livewire) => $livewire instanceof Pages\ListBillets
                ? view('filament.resources.billets.pages.entete-voyage', $livewire->donneesEntete())
                : null)
            ->poll(fn ($livewire) => $livewire instanceof Pages\ListBillets ? '15s' : null)
            ->emptyStateHeading('Aucun billet à afficher')
            ->emptyStateDescription('Les billets apparaîtront ici dès leur achat.')
            ->emptyStateIcon('heroicon-o-ticket')
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
                } catch (AuthorizationException|\DomainException $e) {
                    Notification::make()->title('Opération refusée')->body($e->getMessage())->danger()->send();
                }
            });
    }

    // public static function getPages(): array
    // {
    //     return [
    //         'index' => Pages\ListBillets::route('/'),
    //     ];
    // }
    // public statitc function getNavigationItems(): array
    // {
    //     return[
    //         'items'
    //     ]

    // }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBillets::route('/'),
            'departs' => Pages\DepartsDuJour::route('/departs-du-jour'),
            'reservations' => Pages\ReservationsSemaine::route('/reservations'),
        ];
    }

    public static function getNavigationItems(): array
    {
        return [
            ...parent::getNavigationItems(),

            NavigationItem::make('Départs du jour')
                ->icon('heroicon-o-clock')
                ->group(static::getNavigationGroup())
                ->url(static::getUrl('departs'))
                ->isActiveWhen(fn () => request()->routeIs(static::getRouteBaseName().'.departs')),

            NavigationItem::make('Réservations')
                ->icon('heroicon-o-calendar-days')
                ->group(static::getNavigationGroup())
                ->url(static::getUrl('reservations'))
                ->isActiveWhen(fn () => request()->routeIs(static::getRouteBaseName().'.reservations')),
        ];
    }
}
