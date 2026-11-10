<?php

namespace App\Filament\Resources\Employes;

use App\Models\Chauffeur;
use App\Models\Compagnie;
use App\Models\ContratEmploye;
use App\Models\Employe;
use App\Models\Gare;
use App\Services\Personnel\AccesPersonnel;
use App\Services\Personnel\SoldeConges;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class EmployeResource extends Resource
{
    protected static ?string $model = Employe::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationLabel = 'Personnel';

    protected static ?string $modelLabel = 'employé';

    protected static ?string $pluralModelLabel = 'employés';

    protected static string|UnitEnum|null $navigationGroup = 'Personnel';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'nom';

    public static function canViewAny(): bool
    {
        return AccesPersonnel::visible(auth()->user());
    }

    public static function canCreate(): bool
    {
        return AccesPersonnel::gerant(auth()->user());
    }

    public static function canEdit(Model $record): bool
    {
        return AccesPersonnel::gerant(auth()->user());
    }

    public static function canDelete(Model $record): bool
    {
        return false; // l'historique est conservé : on enregistre une sortie
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->visiblePour(auth()->user())->with(['gare.ville', 'contratActif']);
    }

    protected static function cid(Get $get): int
    {
        $u = auth()->user();

        return $u->isSuperAdmin() ? (int) $get('compagnie_id') : (int) $u->compagnieId();
    }

    protected static function optionsGares(Get $get): array
    {
        $u = auth()->user();

        $q = $u->isSuperAdmin() ? Gare::query()->where('compagnie_id', (int) $get('compagnie_id')) : $u->garesGerables();

        return $q->with('ville')->orderBy('quartier')->get()
            ->mapWithKeys(fn (Gare $g) => [$g->id => $g->quartier.($g->ville ? ' — '.$g->ville->nom : '')])->all();
    }

    /** Impose côté serveur la compagnie et le périmètre de la gare. */
    public static function securiser(array $data, ?Employe $employe = null): array
    {
        $u = auth()->user();

        abort_unless(AccesPersonnel::gerant($u), 403);

        $cid = $u->isSuperAdmin() ? (int) ($data['compagnie_id'] ?? $employe?->compagnie_id) : (int) $u->compagnieId();
        abort_if(! $cid, 403);

        if (! empty($data['gare_id'])) {
            $gare = Gare::findOrFail($data['gare_id']);

            abort_unless((int) $gare->compagnie_id === $cid, 403);
            abort_if($u->isAdminCompagnie() && $u->villeId() && ! $u->garesGerables()->whereKey($gare->id)->exists(), 403);
        } elseif ($u->isAdminCompagnie() && $u->villeId()) {
            throw ValidationException::withMessages(['data.gare_id' => 'Choisissez la gare de l\'employé.']);
        } else {
            $data['gare_id'] = null;
        }

        if (! empty($data['chauffeur_id'])) {
            abort_unless(Chauffeur::whereKey($data['chauffeur_id'])->where('compagnie_id', $cid)->exists(), 403);
        } else {
            $data['chauffeur_id'] = null;
        }

        if (blank($data['matricule'] ?? null)) {
            unset($data['matricule']);
        }

        if (($data['statut'] ?? 'actif') !== 'sorti') {
            $data['date_sortie'] = null;
            $data['motif_sortie'] = null;
        }

        $data['compagnie_id'] = $cid;

        return $data;
    }

    public static function form(Schema $schema): Schema
    {
        $superAdmin = auth()->user()->isSuperAdmin();

        return $schema->components([
            Tabs::make('Fiche')->columnSpanFull()->tabs([
                Tab::make('Identité')->icon('heroicon-o-user')->schema([
                    Section::make()->columns(3)->schema([
                        FileUpload::make('photo')->label('Photo')->image()->avatar()->directory('personnel/photos')->imageEditor(),
                        TextInput::make('matricule')->label('Matricule')->disabled()->dehydrated(false)->placeholder('Attribué automatiquement'),
                        TextInput::make('nom')->label('Nom')->required()->maxLength(80)
                            ->dehydrateStateUsing(fn ($s) => mb_strtoupper(trim((string) $s))),
                        TextInput::make('prenoms')->label('Prénom(s)')->required()->maxLength(120),
                        Select::make('sexe')->label('Sexe')->options(['M' => 'Masculin', 'F' => 'Féminin']),
                        DatePicker::make('date_naissance')->label('Date de naissance')->maxDate(today()->subYears(15)),
                        TextInput::make('lieu_naissance')->label('Lieu de naissance')->maxLength(80),
                        TextInput::make('nationalite')->label('Nationalité')->default('Burkinabè'),
                        TextInput::make('cnib_numero')->label('N° CNIB / passeport')->maxLength(40)->unique(ignoreRecord: true),
                        TextInput::make('telephone')->label('Téléphone')->tel()->maxLength(30),
                        TextInput::make('email')->label('E-mail')->email()->maxLength(120),
                        TextInput::make('adresse')->label('Adresse / quartier')->maxLength(255),
                    ]),
                ]),

                Tab::make('Poste')->icon('heroicon-o-briefcase')->schema([
                    Section::make('Affectation')->columns(3)->schema([
                        Select::make('compagnie_id')->label('Compagnie')
                            ->options(fn () => Compagnie::orderBy('nom')->pluck('nom', 'id')->all())
                            ->required()->live()
                            ->afterStateUpdated(fn (Set $set) => $set('gare_id', null))
                            ->visible($superAdmin),
                        Select::make('gare_id')->label('Gare d\'affectation')
                            ->options(fn (Get $get) => static::optionsGares($get))
                            ->searchable()->placeholder('Siège / sans gare')
                            ->helperText('Laissez vide pour le personnel du siège.'),
                        TextInput::make('poste')->label('Poste occupé')->required()->maxLength(100),
                        Select::make('service')->label('Service')->options(Employe::SERVICES)->default('exploitation')->required()->live(),
                        DatePicker::make('date_embauche')->label('Date d\'embauche')->required()->default(today()),
                        Select::make('statut')->label('Statut')->options(Employe::STATUTS)->default('actif')->required()->live(),
                        DatePicker::make('date_sortie')->label('Date de sortie')->visible(fn (Get $get) => $get('statut') === 'sorti'),
                        TextInput::make('motif_sortie')->label('Motif de sortie')->maxLength(255)->visible(fn (Get $get) => $get('statut') === 'sorti'),
                        Select::make('chauffeur_id')->label('Fiche chauffeur liée')
                            ->options(fn (Get $get) => Chauffeur::where('compagnie_id', static::cid($get))->orderBy('nom')->get()
                                ->mapWithKeys(fn (Chauffeur $c) => [$c->id => $c->nomComplet()])->all())
                            ->searchable()->placeholder('Aucune')
                            ->visible(fn (Get $get) => in_array($get('service'), ['conduite', 'convoyage'], true)),
                    ]),
                    Section::make('Horaires de travail')->description('Servent à repérer les retards au pointage. Vide = horaires par défaut de la compagnie.')->columns(2)->schema([
                        TimePicker::make('horaire_debut')->label('Début de journée')->seconds(false),
                        TimePicker::make('horaire_fin')->label('Fin de journée')->seconds(false),
                    ]),
                ]),

                Tab::make('Famille et urgences')->icon('heroicon-o-heart')->schema([
                    Section::make()->columns(3)->schema([
                        Select::make('situation_familiale')->label('Situation familiale')->options(Employe::SITUATIONS)->default('celibataire')->required(),
                        Toggle::make('conjoint_a_charge')->label('Conjoint(e) à charge')->inline(false),
                        TextInput::make('enfants_a_charge')->label('Enfants à charge')->numeric()->minValue(0)->maxValue(15)->default(0)
                            ->helperText('Sert à la réduction d\'IUTS (4 charges au maximum retenues).'),
                        TextInput::make('contact_urgence_nom')->label('Personne à prévenir')->maxLength(120),
                        TextInput::make('contact_urgence_lien')->label('Lien de parenté')->maxLength(40),
                        TextInput::make('telephone_urgence')->label('Téléphone d\'urgence')->tel()->maxLength(30),
                    ]),
                ]),

                Tab::make('Paiement et CNSS')->icon('heroicon-o-banknotes')->schema([
                    Section::make()->columns(3)->schema([
                        Select::make('mode_paiement')->label('Mode de paiement du salaire')->options(Employe::MODES_PAIEMENT)->default('especes')->required(),
                        TextInput::make('numero_paiement')->label('N° Mobile Money / compte bancaire')->maxLength(60),
                        TextInput::make('cnss_numero')->label('N° CNSS')->maxLength(40),
                        Textarea::make('observations')->label('Observations')->rows(3)->columnSpanFull(),
                    ]),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $gerant = AccesPersonnel::gerant(auth()->user());

        return $table
            ->defaultSort('nom')
            ->columns([
                Stack::make([
                    Split::make([
                        ImageColumn::make('photo')->label('')->circular()->width(48)->height(48),
                        Stack::make([
                            TextColumn::make('nom')->label('Employé')->searchable(['nom', 'prenoms', 'matricule'])->sortable()->weight('bold')
                                ->formatStateUsing(fn ($state, Employe $r) => $r->nomComplet())
                                ->description(fn (Employe $r) => $r->matricule.' · '.$r->poste),
                            TextColumn::make('service')->label('Service')->badge()->color('gray')
                                ->formatStateUsing(fn ($state) => Employe::SERVICES[$state] ?? $state),
                        ]),
                    ])->from('sm'),
                    Split::make([
                        TextColumn::make('gare_id')->label('Affectation')
                            ->formatStateUsing(fn ($state, Employe $r) => $r->gare ? $r->gare->quartier : 'Siège')
                            ->description(fn (Employe $r) => $r->gare?->ville?->nom),
                        TextColumn::make('statut')->label('Statut')->badge()
                            ->formatStateUsing(fn ($state) => Employe::STATUTS[$state] ?? $state)
                            ->color(fn ($state) => match ($state) {
                                'actif' => 'success', 'suspendu' => 'warning', default => 'gray'
                            }),
                    ])->from('sm'),
                    TextColumn::make('contrat')->label('Contrat')->visible($gerant)
                        ->getStateUsing(fn (Employe $r) => $r->contratActif ? (ContratEmploye::TYPES[$r->contratActif->type] ?? $r->contratActif->type) : 'Aucun contrat')
                        ->badge()->color(fn ($state) => $state === 'Aucun contrat' ? 'danger' : 'info')
                        ->description(fn (Employe $r) => $r->contratActif?->date_fin ? 'jusqu\'au '.$r->contratActif->date_fin->format('d/m/Y') : null),
                    Split::make([
                        TextColumn::make('telephone')->label('Téléphone')->placeholder('—'),
                        TextColumn::make('date_embauche')->label('Embauché le')->date('d/m/Y'),
                    ])->from('sm'),
                ])->space(3),
            ])
            ->contentGrid(['md' => 2, 'xl' => 3])
            ->filters([
                SelectFilter::make('statut')->label('Statut')->options(Employe::STATUTS),
                SelectFilter::make('service')->label('Service')->options(Employe::SERVICES),
                SelectFilter::make('gare_id')->label('Gare')->options(fn () => Employe::garesOptionsPour(auth()->user())),
            ])
            ->recordActions([
                EditAction::make()->visible($gerant),
                Action::make('attestation')->label('Attestation de travail')->icon('heroicon-o-document-text')->color('gray')
                    ->visible($gerant && Route::has('personnel.attestation'))
                    ->url(fn (Employe $r) => route('personnel.attestation', $r->id))->openUrlInNewTab(),
                Action::make('sortie')->label('Enregistrer la sortie')->icon('heroicon-o-arrow-right-start-on-rectangle')->color('danger')
                    ->visible(fn (Employe $r) => $gerant && $r->statut !== 'sorti')
                    ->modalHeading('Enregistrer la sortie de l\'employé')
                    ->modalDescription('Son contrat actif sera clôturé. Pensez à établir son attestation de travail et à solder ses avances.')
                    ->schema([
                        DatePicker::make('date_sortie')->label('Date de sortie')->default(today())->required(),
                        Select::make('motif')->label('Motif')->required()->options([
                            'demission' => 'Démission', 'fin_contrat' => 'Fin de contrat', 'licenciement' => 'Licenciement',
                            'retraite' => 'Retraite', 'abandon' => 'Abandon de poste', 'deces' => 'Décès', 'autre' => 'Autre',
                        ]),
                    ])
                    ->action(function (Employe $record, array $data) {
                        abort_unless(AccesPersonnel::gerant(auth()->user()), 403);

                        $record->forceFill([
                            'statut' => 'sorti', 'date_sortie' => $data['date_sortie'], 'motif_sortie' => $data['motif'],
                        ])->save();

                        $record->contrats()->where('actif', true)->update([
                            'actif' => false, 'date_fin' => $data['date_sortie'], 'motif_fin' => $data['motif'],
                        ]);

                        Notification::make()->title('Sortie enregistrée')->success()->send();
                    }),
            ])
            ->striped();
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\BulletinsPaieRelationManager::class,
            RelationManagers\ContratsRelationManager::class,
            RelationManagers\DocumentsRelationManager::class,
            RelationManagers\AvancesRelationManager::class,
            RelationManagers\SanctionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmployes::route('/'),
            'create' => Pages\CreateEmploye::route('/create'),
            'edit' => Pages\EditEmploye::route('/{record}/edit'),
        ];
    }

    /** Pour affichage dans d'autres écrans. */
    public static function soldeConges(Employe $e): string
    {
        $s = SoldeConges::pour($e);

        return $s['solde'].' j (acquis '.$s['droit'].', pris '.$s['pris'].')';
    }
}
