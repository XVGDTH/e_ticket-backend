<?php

namespace App\Filament\Resources\Vehicules;

use App\Filament\Pages\RentabiliteFlotte;
use App\Models\Compagnie;
use App\Models\DepenseVehicule;
use App\Models\Gare;
use App\Models\Vehicule;
use App\Models\VoyageCar;
use App\Services\Flotte\Acces;
use App\Services\Flotte\Alertes;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class VehiculeResource extends Resource
{
    protected static ?string $model = Vehicule::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationLabel = 'Cars';

    protected static ?string $modelLabel = 'car';

    protected static ?string $pluralModelLabel = 'cars';

    protected static ?string $recordTitleAttribute = 'immatriculation';

    protected static string|UnitEnum|null $navigationGroup = 'Flotte';

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        return Acces::visible(auth()->user());
    }

    public static function canCreate(): bool
    {
        return Acces::gerant(auth()->user());
    }

    public static function canEdit(Model $record): bool
    {
        return Acces::gerant(auth()->user());
    }

    public static function canDelete(Model $record): bool
    {
        return Acces::gerant(auth()->user())
            && ! VoyageCar::where('vehicule_id', $record->id)->exists()
            && ! DepenseVehicule::where('vehicule_id', $record->id)->exists();
    }

    public static function getNavigationBadge(): ?string
    {
        $n = Alertes::nombre(auth()->user());

        return $n > 0 ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Documents, permis ou vidanges à traiter';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->visiblePour(auth()->user())->with(['gare', 'compagnie']);
    }

    protected static function optionsGares(Get $get): array
    {
        $u = auth()->user();

        $q = $u->isSuperAdmin()
            ? Gare::query()->where('compagnie_id', (int) $get('compagnie_id'))
            : $u->garesGerables();

        return $q->with('ville')->orderBy('quartier')->get()
            ->mapWithKeys(fn (Gare $g) => [$g->id => $g->quartier.($g->ville ? ' — '.$g->ville->nom : '')])->all();
    }

    /** Impose côté serveur la compagnie, la gare et les droits sur les montants. */
    public static function securiser(array $data, ?Vehicule $vehicule = null): array
    {
        $u = auth()->user();

        abort_unless(Acces::gerant($u), 403);

        $cid = $vehicule
            ? (int) $vehicule->compagnie_id
            : ($u->isSuperAdmin() ? (int) ($data['compagnie_id'] ?? 0) : (int) $u->compagnieId());

        abort_unless($cid, 403);
        $data['compagnie_id'] = $cid;

        if (! empty($data['gare_id'])) {
            $gare = Gare::findOrFail($data['gare_id']);

            abort_unless((int) $gare->compagnie_id === $cid, 403);
            abort_if($u->isAdminCompagnie() && $u->villeId() && ! $u->garesGerables()->whereKey($gare->id)->exists(), 403);
        } else {
            abort_if($u->isAdminCompagnie() && $u->villeId(), 403); // un admin de ville doit choisir une gare
            $data['gare_id'] = null;
        }

        $immat = strtoupper(trim((string) ($data['immatriculation'] ?? '')));

        $existe = Vehicule::where('compagnie_id', $cid)->where('immatriculation', $immat)
            ->when($vehicule, fn ($q) => $q->whereKeyNot($vehicule->id))->exists();

        if ($existe) {
            throw ValidationException::withMessages(['data.immatriculation' => 'Ce car est déjà enregistré.']);
        }

        $data['immatriculation'] = $immat;

        if (! Acces::finance($u)) {
            unset($data['prix_acquisition'], $data['date_acquisition'], $data['date_mise_en_service'], $data['duree_amortissement_ans'], $data['valeur_residuelle']);

            return $data;
        }

        $prix = (int) ($data['prix_acquisition'] ?? 0);
        $data['valeur_residuelle'] = (int) ($data['valeur_residuelle'] ?? 0);

        if ($prix > 0) {
            if ((int) ($data['duree_amortissement_ans'] ?? 0) < 1) {
                throw ValidationException::withMessages(['data.duree_amortissement_ans' => 'Indiquez la durée d\'amortissement, en années.']);
            }

            if ($data['valeur_residuelle'] >= $prix) {
                throw ValidationException::withMessages(['data.valeur_residuelle' => 'La valeur résiduelle doit être inférieure au prix d\'achat.']);
            }

            if (empty($data['date_acquisition']) && empty($data['date_mise_en_service'])) {
                throw ValidationException::withMessages(['data.date_acquisition' => 'Indiquez la date d\'achat ou de mise en service.']);
            }
        }

        return $data;
    }

    public static function form(Schema $schema): Schema
    {
        $superAdmin = auth()->user()->isSuperAdmin();

        return $schema->components([
            Section::make('Identification')->columns(3)->schema([
                Select::make('compagnie_id')
                    ->label('Compagnie')
                    ->options(fn () => Compagnie::orderBy('nom')->pluck('nom', 'id')->all())
                    ->required()
                    ->live()
                    ->disabled(fn (string $operation) => $operation === 'edit')
                    ->dehydrated()
                    ->afterStateUpdated(fn (Set $set) => $set('gare_id', null))
                    ->visible($superAdmin),
                TextInput::make('immatriculation')->label('Immatriculation')->required()->maxLength(20)
                    ->extraInputAttributes(['style' => 'text-transform:uppercase']),
                TextInput::make('numero_parc')->label('Numéro de parc (facultatif)')->maxLength(20),
                TextInput::make('marque')->label('Marque')->maxLength(40),
                TextInput::make('modele')->label('Modèle')->maxLength(40),
                TextInput::make('annee')->label('Année de fabrication')->numeric()->minValue(1980)->maxValue((int) now()->year + 1),
            ]),

            Section::make('Capacité et affectation')->columns(3)->schema([
                TextInput::make('places')->label('Nombre de places')->numeric()->minValue(1)->maxValue(200)->required(),
                Select::make('gare_id')
                    ->label('Gare d\'affectation')
                    ->options(fn (Get $get) => static::optionsGares($get))
                    ->placeholder(fn () => auth()->user()->villeId() ? null : 'Aucune (toute la compagnie)')
                    ->required(fn () => (bool) auth()->user()->villeId())
                    ->searchable(),
                Select::make('statut')->label('État')->options(Vehicule::STATUTS)->default('actif')->required()
                    ->helperText('Seuls les cars « En service » peuvent être affectés à un départ.'),
                Select::make('carburant')->label('Carburant')->options(Vehicule::CARBURANTS)->default('gasoil')->required(),
                TextInput::make('km_actuel')->label('Kilométrage actuel')->numeric()->minValue(0)->default(0)->suffix('km'),
                TextInput::make('km_prochaine_vidange')->label('Prochaine vidange à')->numeric()->minValue(0)->suffix('km')
                    ->helperText('Une alerte apparaît 500 km avant.'),
            ]),

            Section::make('Achat et amortissement')
                ->description('Visible uniquement du super admin et de l\'administrateur de la compagnie. L\'amortissement est linéaire, calculé au jour le jour sur la durée que vous choisissez.')
                ->columns(3)
                ->visible(fn () => Acces::finance(auth()->user()))
                ->schema([
                    TextInput::make('prix_acquisition')->label('Prix d\'achat')->numeric()->minValue(0)->suffix('FCFA')->live(onBlur: true),
                    DatePicker::make('date_acquisition')->label('Date d\'achat')->maxDate(today()),
                    DatePicker::make('date_mise_en_service')->label('Date de mise en service')
                        ->helperText('L\'amortissement commence ce jour-là (sinon, à la date d\'achat).'),
                    TextInput::make('duree_amortissement_ans')->label('Durée d\'amortissement')->numeric()->minValue(1)->maxValue(30)->suffix('ans')->live(onBlur: true)
                        ->helperText(function (Get $get) {
                            $prix = (int) $get('prix_acquisition');
                            $res = (int) $get('valeur_residuelle');
                            $ans = (int) $get('duree_amortissement_ans');

                            if ($prix <= 0 || $ans <= 0 || $res >= $prix) {
                                return 'Durée pendant laquelle le car perd sa valeur dans vos comptes.';
                            }

                            return 'Soit '.number_format(intdiv($prix - $res, $ans), 0, ',', ' ').' FCFA par an, '.number_format(intdiv($prix - $res, $ans * 12), 0, ',', ' ').' FCFA par mois. À valider avec votre comptable.';
                        }),
                    TextInput::make('valeur_residuelle')->label('Valeur résiduelle')->numeric()->minValue(0)->default(0)->suffix('FCFA')->live(onBlur: true)
                        ->helperText('Ce que vaudra le car à la fin de la durée (0 si vous ne l\'estimez pas).'),
                ]),

            Section::make('Documents')->columns(3)->schema([
                DatePicker::make('assurance_expire_le')->label('Assurance valable jusqu\'au'),
                DatePicker::make('visite_technique_expire_le')->label('Visite technique valable jusqu\'au'),
                TextInput::make('autre_doc_libelle')->label('Autre document (nom)')->maxLength(60)->placeholder('Ex. : carte de transport'),
                DatePicker::make('autre_doc_expire_le')->label('Autre document, jusqu\'au'),
                Textarea::make('note')->label('Note')->rows(2)->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('immatriculation')
            ->columns([
                Stack::make([
                    Split::make([
                        Stack::make([
                            TextColumn::make('immatriculation')
                                ->label('Immatriculation')->fontFamily('mono')->weight('bold')->size('lg')->searchable()
                                ->description(fn (Vehicule $r) => trim(($r->marque ?? '').' '.($r->modele ?? '').($r->annee ? ' · '.$r->annee : '')) ?: null),
                            TextColumn::make('compagnie.nom')->label('Compagnie')->visible(fn () => auth()->user()->isSuperAdmin()),
                        ])->space(1),
                        Stack::make([
                            TextColumn::make('statut')
                                ->label('État')->badge()
                                ->formatStateUsing(fn ($state) => Vehicule::STATUTS[$state] ?? $state)
                                ->color(fn ($state) => match ($state) {
                                    'actif' => 'success', 'maintenance' => 'warning', 'panne' => 'danger', default => 'gray',
                                }),
                            TextColumn::make('documents')
                                ->label('Documents')->badge()
                                ->getStateUsing(fn (Vehicule $r) => $r->etatDocuments())
                                ->color(fn ($state) => match ($state) {
                                    'Expirés' => 'danger', 'À renouveler' => 'warning', 'À jour' => 'success', default => 'gray',
                                }),
                        ])->space(1),
                    ])->from('sm'),
                    Split::make([
                        TextColumn::make('places')->label('Places')->numeric()->description('Capacité'),
                        TextColumn::make('gare.quartier')->label('Gare')->placeholder('—')->description('Affectation'),
                        TextColumn::make('km_actuel')->label('Kilométrage')->numeric(0, ',', ' ')->suffix(' km')->toggleable(),
                        TextColumn::make('vnc')
                            ->label('Valeur nette')
                            ->getStateUsing(fn (Vehicule $r) => $r->prix_acquisition ? number_format($r->valeurNetteComptable(today()), 0, ',', ' ').' FCFA' : '—')
                            ->visible(fn () => Acces::finance(auth()->user())),
                    ])->from('sm'),
                ])->space(3),
            ])
            ->contentGrid(['md' => 2, 'xl' => 3])
            ->filters([
                SelectFilter::make('statut')->label('État')->options(Vehicule::STATUTS),
                SelectFilter::make('gare_id')->label('Gare')->relationship('gare', 'quartier'),
            ])
            ->recordActions([
                Action::make('rentabilite')
                    ->label('Rentabilité')
                    ->icon('heroicon-o-chart-bar')
                    ->color('success')
                    ->visible(fn () => Acces::finance(auth()->user()))
                    ->url(fn (Vehicule $r) => RentabiliteFlotte::getUrl(['vehiculeId' => $r->id])),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->striped();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVehicules::route('/'),
            'create' => Pages\CreateVehicule::route('/create'),
            'edit' => Pages\EditVehicule::route('/{record}/edit'),
        ];
    }
}
