<?php

namespace App\Filament\Resources\VoyagesCars;

use App\Models\Chauffeur;
use App\Models\Compagnie;
use App\Models\Gare;
use App\Models\RouteTransport;
use App\Models\Vehicule;
use App\Models\VoyageCar;
use App\Services\Flotte\Acces;
use App\Services\Flotte\Rentabilite;
use App\Services\Guichet\VenteGuichet;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class VoyageCarResource extends Resource
{
    protected static ?string $model = VoyageCar::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-map';

    protected static ?string $navigationLabel = 'Voyages des cars';

    protected static ?string $modelLabel = 'voyage';

    protected static ?string $pluralModelLabel = 'voyages';

    protected static string|UnitEnum|null $navigationGroup = 'Flotte';

    protected static ?int $navigationSort = 3;

    public static function canViewAny(): bool
    {
        return Acces::visible(auth()->user());
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canViewAny() && $record->statut === 'programme';
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->visiblePour(auth()->user())
            ->with(['route.gareDepart', 'route.villeArrivee', 'vehicule', 'chauffeur', 'compagnie']);
    }

    protected static function cid(Get $get): int
    {
        $u = auth()->user();

        return $u->isSuperAdmin() ? (int) $get('compagnie_id') : (int) $u->compagnieId();
    }

    protected static function optionsGares(Get $get): array
    {
        $u = auth()->user();

        $q = match (true) {
            $u->isSuperAdmin() => Gare::query()->where('compagnie_id', (int) $get('compagnie_id')),
            $u->isAdminCompagnie() => $u->garesGerables(),
            default => Gare::query()->whereKey($u->gareId() ?? 0),
        };

        return $q->with('ville')->orderBy('quartier')->get()
            ->mapWithKeys(fn (Gare $g) => [$g->id => $g->quartier.($g->ville ? ' — '.$g->ville->nom : '')])->all();
    }

    protected static function libelleRoute(?RouteTransport $r): string
    {
        return ($r?->gareDepart?->quartier ?? '?').' → '.($r?->villeArrivee?->nom ?? '?');
    }

    /** Impose côté serveur la compagnie, la gare et la validité de l'horaire, du car et du chauffeur. */
    public static function securiser(array $data, ?VoyageCar $voyage = null): array
    {
        $u = auth()->user();

        abort_unless(Acces::visible($u), 403);

        $route = RouteTransport::findOrFail($data['route_transport_id']);
        $cid = (int) $route->compagnie_id;
        $gareId = (int) $route->gare_depart_id;

        abort_unless($u->isSuperAdmin() || $cid === (int) $u->compagnieId(), 403);
        abort_if($u->isAgentGare() && $gareId !== (int) $u->gareId(), 403);
        abort_if($u->isAdminCompagnie() && $u->villeId() && ! $u->garesGerables()->whereKey($gareId)->exists(), 403);

        $heure = Rentabilite::heure((string) $data['heure']);

        $valides = DB::table('horaires')->where('route_transport_id', $route->id)->pluck('heure')
            ->map(fn ($h) => Rentabilite::heure((string) $h))->all();

        if (! in_array($heure, $valides, true)) {
            throw ValidationException::withMessages(['data.heure' => 'Cet horaire n\'existe pas pour cette route.']);
        }

        $car = Vehicule::findOrFail($data['vehicule_id']);

        abort_unless((int) $car->compagnie_id === $cid, 403);

        if ($car->statut !== 'actif') {
            throw ValidationException::withMessages(['data.vehicule_id' => 'Ce car n\'est pas en service ('.(Vehicule::STATUTS[$car->statut] ?? $car->statut).').']);
        }

        if (! empty($data['chauffeur_id'])) {
            abort_unless(Chauffeur::whereKey($data['chauffeur_id'])->where('compagnie_id', $cid)->exists(), 403);
        } else {
            $data['chauffeur_id'] = null;
        }

        $date = Carbon::parse($data['date_voyage'])->startOfDay();

        if (! Acces::gerant($u) && ($date->lt(today()->subDays(7)) || $date->gt(today()->addDays(30)))) {
            throw ValidationException::withMessages(['data.date_voyage' => 'Un agent programme les voyages de 7 jours en arrière à 30 jours en avant.']);
        }

        $deja = VoyageCar::where('vehicule_id', $car->id)->whereDate('date_voyage', $date->toDateString())
            ->where('heure', $heure)->where('statut', '!=', 'annule')
            ->when($voyage, fn ($q) => $q->whereKeyNot($voyage->id))->exists();

        if ($deja) {
            throw ValidationException::withMessages(['data.vehicule_id' => 'Ce car est déjà programmé à cette date et à cette heure.']);
        }

        $data['compagnie_id'] = $cid;
        $data['gare_id'] = $gareId;
        $data['heure'] = $heure;
        $data['date_voyage'] = $date->toDateString();

        if (! $voyage) {
            $data['cree_par'] = $u->id;
            $data['statut'] = 'programme';
        }

        return $data;
    }

    public static function form(Schema $schema): Schema
    {
        $u = auth()->user();
        $superAdmin = $u->isSuperAdmin();

        return $schema->components([
            Section::make('Départ')->columns(3)->schema([
                Select::make('compagnie_id')
                    ->label('Compagnie')
                    ->options(fn () => Compagnie::orderBy('nom')->pluck('nom', 'id')->all())
                    ->required()->live()->dehydrated(false)
                    ->afterStateHydrated(function (Select $component, ?Model $record) {
                        if ($record) {
                            $component->state($record->compagnie_id);
                        }
                    })
                    ->afterStateUpdated(function (Set $set) {
                        $set('gare_id', null);
                        $set('route_transport_id', null);
                        $set('heure', null);
                        $set('vehicule_id', null);
                        $set('chauffeur_id', null);
                    })
                    ->visible($superAdmin),

                Select::make('gare_id')
                    ->label('Gare de départ')
                    ->options(fn (Get $get) => static::optionsGares($get))
                    ->default(fn () => auth()->user()->isAgentGare() ? auth()->user()->gareId() : null)
                    ->disabled(fn () => auth()->user()->isAgentGare())
                    ->dehydrated()
                    ->required()->searchable()->live()
                    ->afterStateUpdated(function (Set $set) {
                        $set('route_transport_id', null);
                        $set('heure', null);
                    }),

                Select::make('route_transport_id')
                    ->label('Destination')
                    ->options(function (Get $get) {
                        $gareId = (int) $get('gare_id');

                        if (! $gareId) {
                            return [];
                        }

                        return RouteTransport::with('villeArrivee')->where('gare_depart_id', $gareId)->get()
                            ->mapWithKeys(fn (RouteTransport $r) => [$r->id => 'Vers '.($r->villeArrivee?->nom ?? '?')])->all();
                    })
                    ->required()->live()
                    ->afterStateUpdated(fn (Set $set) => $set('heure', null)),

                DatePicker::make('date_voyage')->label('Date')->default(today())->required(),

                Select::make('heure')
                    ->label('Heure de départ')
                    ->afterStateHydrated(fn (Select $component, $state) => $state ? $component->state(Rentabilite::heure((string) $state)) : null)
                    ->options(function (Get $get) {
                        $routeId = (int) $get('route_transport_id');

                        if (! $routeId) {
                            return [];
                        }

                        return DB::table('horaires')->where('route_transport_id', $routeId)->orderBy('heure')->pluck('heure')
                            ->map(fn ($h) => Rentabilite::heure((string) $h))->unique()
                            ->mapWithKeys(fn ($h) => [$h => str_replace(':', 'h', $h)])->all();
                    })
                    ->required(),
            ]),

            Section::make('Car et chauffeur')->columns(2)->schema([
                Select::make('vehicule_id')
                    ->label('Car')
                    ->options(fn (Get $get) => Vehicule::where('compagnie_id', static::cid($get))->where('statut', 'actif')->orderBy('immatriculation')->get()
                        ->mapWithKeys(fn (Vehicule $v) => [$v->id => $v->immatriculation.' · '.$v->places.' places'])->all())
                    ->required()->searchable()->live()
                    ->afterStateUpdated(function ($state, Set $set) {
                        $habituel = $state ? Chauffeur::where('vehicule_habituel_id', $state)->where('actif', true)->value('id') : null;

                        if ($habituel) {
                            $set('chauffeur_id', $habituel);
                        }
                    }),
                Select::make('chauffeur_id')
                    ->label('Chauffeur')
                    ->options(fn (Get $get) => Chauffeur::where('compagnie_id', static::cid($get))->where('actif', true)->orderBy('nom')->get()
                        ->mapWithKeys(fn (Chauffeur $c) => [$c->id => $c->nomComplet()])->all())
                    ->searchable()
                    ->placeholder('Non renseigné'),
                Textarea::make('observations')->label('Observations')->rows(2)->maxLength(500)->columnSpanFull(),
            ]),
        ]);
    }

    // ------------------------------------------------------------------ actions

    protected static function executer(\Closure $faire, string $titre): void
    {
        try {
            $faire();
            Notification::make()->title($titre)->success()->send();
        } catch (AuthorizationException|\DomainException $e) {
            Notification::make()->title('Action refusée')->body($e->getMessage())->danger()->send();
        }
    }

    public static function actionCloturer(): Action
    {
        return Action::make('cloturer')
            ->label('Clôturer')
            ->icon('heroicon-o-flag')
            ->color('success')
            ->visible(fn (VoyageCar $r) => in_array($r->statut, ['programme', 'parti'], true) && $r->date_voyage->lte(today()))
            ->modalHeading('Clôturer le voyage')
            ->modalDescription('Saisissez ce qui s\'est réellement passé : ces chiffres alimentent la rentabilité du car. Laissez 0 ou vide ce qui n\'existe pas.')
            ->modalWidth('3xl')
            ->fillForm(function (VoyageCar $r) {
                $guichet = VenteGuichet::partVoyage($r);

                return [
                    'frais_chauffeur' => ($r->chauffeur && $r->chauffeur->remuneration_type === 'par_voyage' && Acces::finance(auth()->user()) && $r->chauffeur->remuneration_montant > 0)
                        ? (int) $r->chauffeur->remuneration_montant : null,
                    'places_guichet' => $guichet['places'],
                    'recette_guichet' => $guichet['montant'],
                ];
            })
            ->schema([
                Section::make('Le trajet')->columns(3)->schema([
                    TextInput::make('km_parcourus')->label('Kilomètres parcourus')->numeric()->minValue(1)->maxValue(5000)->required()->suffix('km')
                        ->helperText('Aller seulement, ou aller et retour si le car revient dans la journée.'),
                    TextInput::make('places_guichet')->label('Places vendues au guichet')->numeric()->minValue(0)->maxValue(300)->default(0)
                        ->helperText('Prérempli à partir des ventes du guichet.'),
                    TextInput::make('recette_guichet')->label('Recette du guichet')->numeric()->minValue(0)->default(0)->suffix('FCFA')
                        ->helperText('Prérempli à partir des ventes du guichet.'),
                    TextInput::make('recette_colis')->label('Recette des colis transportés')->numeric()->minValue(0)->default(0)->suffix('FCFA'),
                ]),
                Section::make('Les dépenses de la route')->columns(3)->schema([
                    TextInput::make('carburant_litres')->label('Carburant, litres')->numeric()->minValue(0)->step('any')->suffix('L'),
                    TextInput::make('carburant_montant')->label('Carburant, montant')->numeric()->minValue(0)->suffix('FCFA'),
                    TextInput::make('peages')->label('Péages')->numeric()->minValue(0)->suffix('FCFA'),
                    TextInput::make('frais_chauffeur')->label('Frais du chauffeur')->numeric()->minValue(0)->suffix('FCFA'),
                    TextInput::make('autres_frais')->label('Autres frais')->numeric()->minValue(0)->suffix('FCFA'),
                    Textarea::make('observations')->label('Observations')->rows(2)->columnSpanFull(),
                ]),
            ])
            ->action(fn (VoyageCar $record, array $data) => static::executer(
                fn () => $record->cloturer(auth()->user(), $data),
                'Voyage clôturé'
            ));
    }

    public static function actionAnnuler(): Action
    {
        return Action::make('annuler')
            ->label('Annuler le voyage')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (VoyageCar $r) => in_array($r->statut, ['programme', 'parti'], true))
            ->requiresConfirmation()
            ->modalDescription('Le voyage ne comptera plus dans la rentabilité du car.')
            ->action(fn (VoyageCar $record) => static::executer(fn () => $record->annuler(auth()->user()), 'Voyage annulé'));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('date_voyage', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->orderByDesc('heure'))
            ->columns([
                TextColumn::make('date_voyage')->label('Date')->date('d/m/Y')->sortable()
                    ->description(fn (VoyageCar $r) => str_replace(':', 'h', $r->heure)),
                TextColumn::make('trajet')
                    ->label('Trajet')
                    ->getStateUsing(fn (VoyageCar $r) => static::libelleRoute($r->route))
                    ->description(fn (VoyageCar $r) => auth()->user()->isSuperAdmin() ? $r->compagnie?->nom : null),
                TextColumn::make('vehicule.immatriculation')->label('Car')->fontFamily('mono')->weight('bold')
                    ->description(fn (VoyageCar $r) => $r->vehicule ? $r->vehicule->places.' places' : null),
                TextColumn::make('chauffeur')
                    ->label('Chauffeur')
                    ->getStateUsing(fn (VoyageCar $r) => $r->chauffeur?->nomComplet())
                    ->placeholder('—'),
                TextColumn::make('statut')
                    ->label('État')->badge()
                    ->formatStateUsing(fn ($state) => VoyageCar::STATUTS[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'termine' => 'success', 'parti' => 'info', 'programme' => 'warning', default => 'gray',
                    }),
                TextColumn::make('km_parcourus')->label('Distance')->numeric(0, ',', ' ')->suffix(' km')->alignEnd()->placeholder('—'),
                TextColumn::make('recette_guichet')->label('Guichet')->numeric(0, ',', ' ')->suffix(' FCFA')->alignEnd()
                    ->description(fn (VoyageCar $r) => $r->places_guichet ? $r->places_guichet.' place(s)' : null),
            ])
            ->filters([
                SelectFilter::make('statut')->label('État')->options(VoyageCar::STATUTS),
                SelectFilter::make('vehicule_id')->label('Car')->relationship('vehicule', 'immatriculation'),
                Filter::make('a_cloturer')
                    ->label('À clôturer')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->whereIn('statut', ['programme', 'parti'])->whereDate('date_voyage', '<', today())),
            ])
            ->recordActions([
                static::actionCloturer(),
                EditAction::make(),
                static::actionAnnuler(),
            ])
            ->striped();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVoyagesCars::route('/'),
            'create' => Pages\CreateVoyageCar::route('/create'),
            'edit' => Pages\EditVoyageCar::route('/{record}/edit'),
        ];
    }
}
