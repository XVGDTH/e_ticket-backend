<?php

namespace App\Filament\Resources\Chauffeurs;

use App\Models\Chauffeur;
use App\Models\Compagnie;
use App\Models\Gare;
use App\Models\Vehicule;
use App\Models\VoyageCar;
use App\Services\Flotte\Acces;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class ChauffeurResource extends Resource
{
    protected static ?string $model = Chauffeur::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-circle';

    protected static ?string $navigationLabel = 'Chauffeurs';

    protected static ?string $modelLabel = 'chauffeur';

    protected static ?string $pluralModelLabel = 'chauffeurs';

    protected static string|UnitEnum|null $navigationGroup = 'Flotte';

    protected static ?int $navigationSort = 2;

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
        return Acces::gerant(auth()->user()) && ! VoyageCar::where('chauffeur_id', $record->id)->exists();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->visiblePour(auth()->user())->with(['gare', 'vehiculeHabituel', 'compagnie']);
    }

    protected static function cid(Get $get): int
    {
        $u = auth()->user();

        return $u->isSuperAdmin() ? (int) $get('compagnie_id') : (int) $u->compagnieId();
    }

    public static function securiser(array $data, ?Chauffeur $chauffeur = null): array
    {
        $u = auth()->user();

        abort_unless(Acces::gerant($u), 403);

        $cid = $chauffeur
            ? (int) $chauffeur->compagnie_id
            : ($u->isSuperAdmin() ? (int) ($data['compagnie_id'] ?? 0) : (int) $u->compagnieId());

        abort_unless($cid, 403);
        $data['compagnie_id'] = $cid;

        if (! empty($data['gare_id'])) {
            $gare = Gare::findOrFail($data['gare_id']);

            abort_unless((int) $gare->compagnie_id === $cid, 403);
            abort_if($u->isAdminCompagnie() && $u->villeId() && ! $u->garesGerables()->whereKey($gare->id)->exists(), 403);
        } else {
            abort_if($u->isAdminCompagnie() && $u->villeId(), 403);
            $data['gare_id'] = null;
        }

        if (! empty($data['vehicule_habituel_id'])) {
            abort_unless(Vehicule::whereKey($data['vehicule_habituel_id'])->where('compagnie_id', $cid)->exists(), 403);
        } else {
            $data['vehicule_habituel_id'] = null;
        }

        if (! Acces::finance($u)) {
            unset($data['remuneration_type'], $data['remuneration_montant']);
        }

        return $data;
    }

    public static function form(Schema $schema): Schema
    {
        $superAdmin = auth()->user()->isSuperAdmin();

        return $schema->components([
            Section::make('Identité')->columns(3)->schema([
                Select::make('compagnie_id')
                    ->label('Compagnie')
                    ->options(fn () => Compagnie::orderBy('nom')->pluck('nom', 'id')->all())
                    ->required()->live()
                    ->disabled(fn (string $operation) => $operation === 'edit')
                    ->dehydrated()
                    ->afterStateUpdated(function (Set $set) {
                        $set('gare_id', null);
                        $set('vehicule_habituel_id', null);
                    })
                    ->visible($superAdmin),
                TextInput::make('prenom')->label('Prénom')->required()->maxLength(100),
                TextInput::make('nom')->label('Nom')->required()->maxLength(100),
                TextInput::make('telephone')->label('Téléphone')->tel()->maxLength(30),
                DatePicker::make('date_embauche')->label('Date d\'embauche'),
            ]),

            Section::make('Permis de conduire')->columns(3)->schema([
                TextInput::make('permis_numero')->label('Numéro du permis')->maxLength(40),
                TextInput::make('permis_categorie')->label('Catégorie')->maxLength(10)->placeholder('Ex. : D'),
                DatePicker::make('permis_expire_le')->label('Valable jusqu\'au')
                    ->helperText('Une alerte apparaît 30 jours avant l\'expiration.'),
            ]),

            Section::make('Affectation')->columns(2)->schema([
                Select::make('gare_id')
                    ->label('Gare')
                    ->options(function (Get $get) {
                        $u = auth()->user();

                        $q = $u->isSuperAdmin()
                            ? Gare::query()->where('compagnie_id', (int) $get('compagnie_id'))
                            : $u->garesGerables();

                        return $q->with('ville')->orderBy('quartier')->get()
                            ->mapWithKeys(fn (Gare $g) => [$g->id => $g->quartier.($g->ville ? ' — '.$g->ville->nom : '')])->all();
                    })
                    ->placeholder(fn () => auth()->user()->villeId() ? null : 'Aucune (toute la compagnie)')
                    ->required(fn () => (bool) auth()->user()->villeId())
                    ->searchable(),
                Select::make('vehicule_habituel_id')
                    ->label('Car habituel')
                    ->options(fn (Get $get) => Vehicule::where('compagnie_id', static::cid($get))->orderBy('immatriculation')->get()
                        ->mapWithKeys(fn (Vehicule $v) => [$v->id => $v->libelle()])->all())
                    ->searchable()
                    ->placeholder('Aucun'),
                Toggle::make('actif')->label('En activité')->default(true),
            ]),

            Section::make('Rémunération')
                ->description('Visible uniquement du super admin et de l\'administrateur de la compagnie. Elle préremplit les frais du chauffeur à la clôture d\'un voyage.')
                ->columns(2)
                ->visible(fn () => Acces::finance(auth()->user()))
                ->schema([
                    Select::make('remuneration_type')
                        ->label('Mode')
                        ->options(['par_voyage' => 'Par voyage', 'mensuel' => 'Salaire mensuel (saisi à part)'])
                        ->default('par_voyage')
                        ->required(),
                    TextInput::make('remuneration_montant')
                        ->label('Montant')->numeric()->minValue(0)->default(0)->suffix('FCFA'),
                    Textarea::make('note')->label('Note')->rows(2)->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('nom')
            ->columns([
                TextColumn::make('nom')
                    ->label('Chauffeur')->weight('bold')->searchable(['nom', 'prenom'])
                    ->formatStateUsing(fn ($state, Chauffeur $r) => $r->nomComplet())
                    ->description(fn (Chauffeur $r) => $r->telephone),
                TextColumn::make('compagnie.nom')->label('Compagnie')->visible(fn () => auth()->user()->isSuperAdmin()),
                TextColumn::make('gare.quartier')->label('Gare')->placeholder('—'),
                TextColumn::make('vehiculeHabituel.immatriculation')->label('Car habituel')->placeholder('—')->fontFamily('mono'),
                TextColumn::make('permis')
                    ->label('Permis')->badge()
                    ->getStateUsing(fn (Chauffeur $r) => $r->permisEtat())
                    ->description(fn (Chauffeur $r) => $r->permis_expire_le?->format('d/m/Y'))
                    ->color(fn ($state) => match ($state) {
                        'Expiré' => 'danger', 'À renouveler' => 'warning', 'Valide' => 'success', default => 'gray',
                    }),
                TextColumn::make('remuneration')
                    ->label('Rémunération')
                    ->getStateUsing(fn (Chauffeur $r) => $r->remuneration_montant
                        ? number_format($r->remuneration_montant, 0, ',', ' ').' FCFA'.($r->remuneration_type === 'par_voyage' ? ' / voyage' : ' / mois')
                        : '—')
                    ->visible(fn () => Acces::finance(auth()->user())),
                IconColumn::make('actif')->label('Actif')->boolean(),
            ])
            ->filters([
                SelectFilter::make('actif')->label('Activité')->options([1 => 'En activité', 0 => 'Hors activité']),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->striped();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListChauffeurs::route('/'),
            'create' => Pages\CreateChauffeur::route('/create'),
            'edit' => Pages\EditChauffeur::route('/{record}/edit'),
        ];
    }
}
