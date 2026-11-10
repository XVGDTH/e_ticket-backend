<?php

namespace App\Filament\Resources\FraisRegles;

use App\Models\Compagnie;
use App\Models\FraisRegle;
use App\Models\Gare;
use App\Models\RouteTransport;
use App\Models\Ville;
use App\Services\Finance\RapportFinancier;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class FraisRegleResource extends Resource
{
    protected static ?string $model = FraisRegle::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-receipt-percent';

    // protected static ?string $navigationLabel = 'Tarifs des frais';
    protected static ?string $navigationLabel = 'Tarifs avancés';

    protected static ?string $modelLabel = 'tarif de frais';

    protected static ?string $pluralModelLabel = 'tarifs de frais';

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 4;

    public static function canViewAny(): bool
    {
        return RapportFinancier::autorise(auth()->user());
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return static::autorisePour($record);
    }

    public static function canDelete(Model $record): bool
    {
        return static::autorisePour($record);
    }

    protected static function autorisePour(FraisRegle $regle): bool
    {
        $u = auth()->user();

        if ($u->isSuperAdmin()) {
            return true;
        }

        if (! $u->isAdminCompagnie() || (int) $regle->compagnie_id !== (int) $u->compagnieId()) {
            return false;
        }

        return ! $u->villeId() || ($regle->gare_id !== null && $u->garesGerables()->whereKey($regle->gare_id)->exists());
    }

    public static function getEloquentQuery(): Builder
    {
        $q = parent::getEloquentQuery()->with(['compagnie', 'gare']);
        $u = auth()->user();

        if ($u->isSuperAdmin()) {
            return $q;
        }

        if ($u->isAdminCompagnie()) {
            $q->where('compagnie_id', $u->compagnieId() ?? 0);

            if ($u->villeId()) {
                $q->whereIn('gare_id', $u->garesGerables()->select('gares.id'));
            }

            return $q;
        }

        return $q->whereRaw('1 = 0');
    }

    /** Routes que l'utilisateur peut choisir (simulateur). */
    public static function routesVisibles(): array
    {
        $u = auth()->user();
        $villes = Ville::pluck('nom', 'id');
        $q = RouteTransport::query()->with(['gareDepart', 'compagnie']);

        if (! $u->isSuperAdmin()) {
            $q->where('compagnie_id', $u->compagnieId() ?? 0);

            if ($u->villeId()) {
                $q->whereIn('gare_depart_id', $u->garesGerables()->select('gares.id'));
            }
        }

        return $q->get()->mapWithKeys(fn (RouteTransport $r) => [
            $r->id => ($u->isSuperAdmin() ? ($r->compagnie?->nom.' · ') : '')
                .($r->gareDepart?->quartier ?? '?').' → '.($villes[$r->ville_arrivee_id] ?? '?'),
        ])->all();
    }

    /** Impose côté serveur la compagnie, la gare et la route, quoi que le formulaire ait envoyé. */
    public static function securiser(array $data): array
    {
        $u = auth()->user();

        if (! $u->isSuperAdmin()) {
            abort_unless($u->isAdminCompagnie() && $u->compagnieId(), 403);
            $data['compagnie_id'] = $u->compagnieId();

            if (! empty($data['gare_id'])) {
                abort_unless($u->garesGerables()->whereKey($data['gare_id'])->exists(), 403);
            } else {
                abort_if($u->villeId(), 403); // admin limité à une ville : la gare est obligatoire
            }
        }

        if (! empty($data['route_transport_id'])) {
            $route = RouteTransport::findOrFail($data['route_transport_id']);

            abort_if(! empty($data['gare_id']) && (int) $route->gare_depart_id !== (int) $data['gare_id'], 403);
            abort_if(! $u->isSuperAdmin() && (int) $route->compagnie_id !== (int) $u->compagnieId(), 403);

            $data['gare_id'] = $route->gare_depart_id;
            $data['compagnie_id'] = $route->compagnie_id;
        } elseif (! empty($data['gare_id'])) {
            $data['compagnie_id'] = Gare::whereKey($data['gare_id'])->value('compagnie_id');
        }

        if (($data['mode'] ?? 'fixe') === 'fixe') {
            $data['frais_min'] = null;
            $data['frais_max'] = null;
        }

        return $data;
    }

    public static function form(Schema $schema): Schema
    {
        $u = auth()->user();
        $superAdmin = $u->isSuperAdmin();

        return $schema->components([
            Section::make('Où ce tarif s\'applique-t-il ?')
                ->description('Plus le tarif est précis (route, puis gare, puis compagnie), plus il passe en priorité.')
                ->columns(2)
                ->schema([
                    $superAdmin
                        ? Select::make('compagnie_id')
                            ->label('Compagnie')
                            ->options(fn () => Compagnie::orderBy('nom')->pluck('nom', 'id')->all())
                            ->placeholder('Toutes les compagnies')
                            ->live()
                            ->afterStateUpdated(function (Set $set) {
                                $set('gare_id', null);
                                $set('route_transport_id', null);
                            })
                        : Hidden::make('compagnie_id')->default($u->compagnieId()),

                    Select::make('gare_id')
                        ->label('Gare de départ')
                        ->placeholder('Toutes les gares')
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(fn (Set $set) => $set('route_transport_id', null))
                        ->required(fn () => (bool) auth()->user()->villeId())
                        ->options(function (Get $get) {
                            $u = auth()->user();
                            $compagnieId = $u->isSuperAdmin() ? (int) $get('compagnie_id') : $u->compagnieId();

                            $gares = $u->isSuperAdmin()
                                ? Gare::query()->when($compagnieId, fn ($q) => $q->where('compagnie_id', $compagnieId))
                                : $u->garesGerables();

                            return $gares->with(['ville', 'compagnie'])->orderBy('quartier')->get()
                                ->mapWithKeys(fn (Gare $g) => [
                                    $g->id => $g->quartier
                                        .($g->ville ? ' — '.$g->ville->nom : '')
                                        .($u->isSuperAdmin() && ! $compagnieId ? ' · '.($g->compagnie?->nom ?? '') : ''),
                                ])->all();
                        }),

                    Select::make('route_transport_id')
                        ->label('Route précise')
                        ->placeholder('Toutes les routes de la gare')
                        ->helperText('Choisissez d\'abord une gare.')
                        ->searchable()
                        ->options(function (Get $get) {
                            $gareId = (int) $get('gare_id');

                            if (! $gareId) {
                                return [];
                            }

                            $villes = Ville::pluck('nom', 'id');

                            return RouteTransport::where('gare_depart_id', $gareId)->get()
                                ->mapWithKeys(fn (RouteTransport $r) => [$r->id => 'Vers '.($villes[$r->ville_arrivee_id] ?? '?')])
                                ->all();
                        }),

                    Select::make('trajet')
                        ->label('Type de trajet')
                        ->placeholder('National et international')
                        ->options(['national' => 'National', 'international' => 'International']),
                ]),

            Section::make('Pour quels billets ?')
                ->columns(2)
                ->schema([
                    TextInput::make('prix_min')
                        ->label('Prix du billet, à partir de')
                        ->helperText('Prix d\'un billet pour un voyageur.')
                        ->numeric()->minValue(0)->default(0)->required()->suffix('FCFA'),
                    TextInput::make('prix_max')
                        ->label('Jusqu\'à')
                        ->helperText('Laisser vide pour ne pas limiter.')
                        ->numeric()->gte('prix_min')->suffix('FCFA'),
                ]),

            Section::make('Quels frais ?')
                ->columns(2)
                ->schema([
                    Radio::make('mode')
                        ->label('Calcul')
                        ->options(['fixe' => 'Montant fixe', 'pourcentage' => 'Pourcentage du prix des billets'])
                        ->default('fixe')
                        ->inline()
                        ->live()
                        ->required()
                        ->columnSpanFull(),
                    TextInput::make('valeur')
                        ->label('Valeur')
                        ->numeric()->minValue(0)->required()
                        ->maxValue(fn (Get $get) => $get('mode') === 'pourcentage' ? 100 : 1000000)
                        ->suffix(fn (Get $get) => $get('mode') === 'pourcentage' ? '%' : 'FCFA')
                        ->helperText('Les frais sont payés une seule fois par achat.'),
                    TextInput::make('frais_min')
                        ->label('Frais minimum')->numeric()->minValue(0)->suffix('FCFA')
                        ->visible(fn (Get $get) => $get('mode') === 'pourcentage'),
                    TextInput::make('frais_max')
                        ->label('Frais maximum')->numeric()->minValue(0)->suffix('FCFA')
                        ->visible(fn (Get $get) => $get('mode') === 'pourcentage'),
                    TextInput::make('note')->label('Note (facultatif)')->maxLength(120)->columnSpanFull(),
                    Toggle::make('actif')->label('Tarif actif')->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('portee')
                    ->label('S\'applique à')
                    ->getStateUsing(fn (FraisRegle $r) => $r->libellePortee())
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'Route précise' => 'danger',
                        'Gare' => 'warning',
                        'Compagnie' => 'info',
                        default => 'gray',
                    })
                    ->description(fn (FraisRegle $r) => $r->libelleDetail()),
                TextColumn::make('trajet')
                    ->label('Trajet')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'national' => 'National',
                        'international' => 'International',
                        default => 'Tous',
                    }),
                TextColumn::make('tranche')->label('Prix du billet')->getStateUsing(fn (FraisRegle $r) => $r->libelleTranche()),
                TextColumn::make('frais')->label('Frais')->getStateUsing(fn (FraisRegle $r) => $r->libelleFrais())->weight('bold')->color('primary'),
                IconColumn::make('actif')->label('Actif')->boolean(),
                TextColumn::make('note')->label('Note')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->striped();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFraisRegles::route('/'),
            'create' => Pages\CreateFraisRegle::route('/create'),
            'edit' => Pages\EditFraisRegle::route('/{record}/edit'),
        ];
    }
}
