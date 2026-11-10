<?php

namespace App\Filament\Resources\DepensesVehicules;

use App\Models\DepenseVehicule;
use App\Models\Vehicule;
use App\Services\Flotte\Acces;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class DepenseVehiculeResource extends Resource
{
    protected static ?string $model = DepenseVehicule::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Dépenses des cars';

    protected static ?string $modelLabel = 'dépense';

    protected static ?string $pluralModelLabel = 'dépenses';

    protected static string|UnitEnum|null $navigationGroup = 'Flotte';

    protected static ?int $navigationSort = 4;

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
        return Acces::gerant(auth()->user());
    }

    public static function canDelete(Model $record): bool
    {
        return Acces::gerant(auth()->user());
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->visiblePour(auth()->user())->with(['vehicule', 'createur']);
    }

    public static function categoriesPermises(): array
    {
        $u = auth()->user();

        return $u->isAgentGare()
            ? collect(DepenseVehicule::CATEGORIES)->only(DepenseVehicule::CATEGORIES_AGENT)->all()
            : DepenseVehicule::CATEGORIES;
    }

    public static function securiser(array $data, ?DepenseVehicule $depense = null): array
    {
        $u = auth()->user();

        abort_unless(Acces::visible($u), 403);

        $car = Vehicule::findOrFail($data['vehicule_id']);

        abort_unless($u->isSuperAdmin() || (int) $car->compagnie_id === (int) $u->compagnieId(), 403);
        abort_unless(array_key_exists($data['categorie'] ?? '', static::categoriesPermises()), 403);

        $date = Carbon::parse($data['date_depense'])->startOfDay();

        if ($date->gt(today())) {
            throw ValidationException::withMessages(['data.date_depense' => 'La date ne peut pas être dans le futur.']);
        }

        if (! Acces::gerant($u) && $date->lt(today()->subDays(7))) {
            throw ValidationException::withMessages(['data.date_depense' => 'Un agent saisit les dépenses des 7 derniers jours.']);
        }

        $du = $data['couvre_du'] ?? null;
        $au = $data['couvre_au'] ?? null;

        if (blank($du) !== blank($au)) {
            throw ValidationException::withMessages(['data.couvre_au' => 'Indiquez le début et la fin de la période couverte, ou aucune des deux.']);
        }

        if ($du && Carbon::parse($au)->lt(Carbon::parse($du))) {
            throw ValidationException::withMessages(['data.couvre_au' => 'La fin de la période doit suivre son début.']);
        }

        if (! in_array($data['categorie'], DepenseVehicule::CATEGORIES_PERIODE, true)) {
            $data['couvre_du'] = null;
            $data['couvre_au'] = null;
        }

        if ($data['categorie'] !== 'carburant') {
            $data['litres'] = null;
        }

        $data['compagnie_id'] = (int) $car->compagnie_id;

        if (! $depense) {
            $data['cree_par'] = $u->id;
            $data['source'] = 'saisie';
        }

        return $data;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('La dépense')->columns(3)->schema([
                Select::make('vehicule_id')
                    ->label('Car')
                    ->options(function () {
                        $u = auth()->user();

                        $q = Vehicule::query()->with('compagnie')->orderBy('immatriculation');

                        if (! $u->isSuperAdmin()) {
                            $q->where('compagnie_id', $u->compagnieId() ?? 0);
                        }

                        return $q->get()->mapWithKeys(fn (Vehicule $v) => [
                            $v->id => $v->immatriculation.($u->isSuperAdmin() ? ' ('.($v->compagnie?->nom ?? '').')' : ''),
                        ])->all();
                    })
                    ->required()->searchable(),
                DatePicker::make('date_depense')->label('Date')->default(today())->maxDate(today())->required(),
                Select::make('categorie')
                    ->label('Catégorie')
                    ->options(fn () => static::categoriesPermises())
                    ->required()->live(),
                TextInput::make('montant')->label('Montant')->numeric()->minValue(1)->required()->suffix('FCFA'),
                TextInput::make('litres')->label('Litres')->numeric()->minValue(0)->step('any')->suffix('L')
                    ->visible(fn (Get $get) => $get('categorie') === 'carburant'),
                TextInput::make('kilometrage')->label('Compteur du car')->numeric()->minValue(0)->suffix('km')
                    ->helperText('Facultatif : met à jour le kilométrage du car s\'il est plus élevé.'),
            ]),

            Section::make('Période couverte')
                ->description('Une assurance annuelle payée en une fois : indiquez la période qu\'elle couvre. Son coût sera réparti sur ces jours dans la rentabilité.')
                ->columns(2)
                ->visible(fn (Get $get) => in_array($get('categorie'), DepenseVehicule::CATEGORIES_PERIODE, true))
                ->schema([
                    DatePicker::make('couvre_du')->label('Du'),
                    DatePicker::make('couvre_au')->label('Au'),
                ]),

            Section::make('Justificatif')->columns(2)->schema([
                TextInput::make('fournisseur')->label('Fournisseur ou garage')->maxLength(100),
                TextInput::make('reference')->label('N° de facture ou de reçu')->maxLength(60),
                TextInput::make('note')->label('Note')->maxLength(255)->columnSpanFull(),
                FileUpload::make('piece')
                    ->label('Pièce justificative (photo ou PDF)')
                    ->disk('local')->directory('flotte/pieces')->visibility('private')
                    ->maxSize(8192)
                    ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('date_depense', 'desc')
            ->columns([
                Stack::make([
                    Split::make([
                        Stack::make([
                            TextColumn::make('vehicule.immatriculation')->label('Car')->fontFamily('mono')->weight('bold')->searchable(),
                            TextColumn::make('date_depense')->label('Date')->date('d/m/Y')->sortable(),
                        ])->space(1),
                        TextColumn::make('montant')->label('Montant')->numeric(0, ',', ' ')->suffix(' FCFA')->weight('bold')->size('lg')
                            ->description(fn (DepenseVehicule $r) => $r->litres ? number_format($r->litres, 1, ',', ' ').' L' : null),
                    ])->from('sm'),
                    Split::make([
                        TextColumn::make('categorie')
                            ->label('Catégorie')->badge()
                            ->formatStateUsing(fn ($state) => DepenseVehicule::CATEGORIES[$state] ?? $state)
                            ->color(fn ($state) => match (DepenseVehicule::groupe((string) $state)) {
                                'carburant' => 'warning', 'entretien' => 'danger', 'fixes' => 'info', 'chauffeur' => 'success', default => 'gray',
                            }),
                        TextColumn::make('fournisseur')->label('Fournisseur')->placeholder('—')
                            ->description(fn (DepenseVehicule $r) => $r->reference),
                        TextColumn::make('source')
                            ->label('Origine')->badge()
                            ->formatStateUsing(fn ($state) => $state === 'cloture' ? 'Clôture de voyage' : 'Saisie')
                            ->color(fn ($state) => $state === 'cloture' ? 'info' : 'gray')
                            ->toggleable(),
                        TextColumn::make('createur.prenom')->label('Saisi par')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                        IconColumn::make('piece')->label('Pièce')->boolean()->getStateUsing(fn (DepenseVehicule $r) => filled($r->piece)),
                    ])->from('sm'),
                ])->space(3),
            ])
            ->contentGrid(['md' => 2, 'xl' => 3])
            ->filters([
                SelectFilter::make('vehicule_id')->label('Car')->relationship('vehicule', 'immatriculation'),
                SelectFilter::make('categorie')->label('Catégorie')->options(DepenseVehicule::CATEGORIES),
                Filter::make('ce_mois')->label('Ce mois-ci')->toggle()
                    ->query(fn (Builder $q) => $q->whereDate('date_depense', '>=', today()->startOfMonth())),
            ])
            ->recordActions([
                Action::make('piece')
                    ->label('Voir la pièce')
                    ->icon('heroicon-o-paper-clip')
                    ->color('gray')
                    ->visible(fn (DepenseVehicule $r) => filled($r->piece))
                    ->url(fn (DepenseVehicule $r) => route('flotte.piece', $r->id))
                    ->openUrlInNewTab(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->striped();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDepensesVehicules::route('/'),
            'create' => Pages\CreateDepenseVehicule::route('/create'),
            'edit' => Pages\EditDepenseVehicule::route('/{record}/edit'),
        ];
    }
}
