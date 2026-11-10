<?php

namespace App\Filament\Resources\PeriodesPaie;

use App\Models\Compagnie;
use App\Models\PeriodePaie;
use App\Services\Personnel\AccesPersonnel;
use Carbon\Carbon;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class PeriodePaieResource extends Resource
{
    protected static ?string $model = PeriodePaie::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Paie';

    protected static ?string $modelLabel = 'période de paie';

    protected static ?string $pluralModelLabel = 'périodes de paie';

    protected static string|UnitEnum|null $navigationGroup = 'Personnel';

    protected static ?int $navigationSort = 4;

    public static function canViewAny(): bool
    {
        return AccesPersonnel::paie(auth()->user());
    }

    public static function canCreate(): bool
    {
        return AccesPersonnel::paieGestion(auth()->user());
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
        return parent::getEloquentQuery()->visiblePour(auth()->user())->with('compagnie');
    }

    public static function form(Schema $schema): Schema
    {
        $superAdmin = auth()->user()->isSuperAdmin();

        $mois = collect(range(1, 12))->mapWithKeys(fn ($m) => [$m => ucfirst(Carbon::create(2026, $m, 1)->locale('fr')->translatedFormat('F'))])->all();

        return $schema->components([
            Select::make('compagnie_id')->label('Compagnie')->options(fn () => Compagnie::orderBy('nom')->pluck('nom', 'id')->all())
                ->required()->live()->visible($superAdmin),
            Select::make('annee')->label('Année')->options(collect(range(today()->year - 1, today()->year + 1))->mapWithKeys(fn ($a) => [$a => (string) $a])->all())
                ->default(today()->year)->required(),
            Select::make('mois')->label('Mois')->options($mois)->default(today()->subMonth()->month)->required()
                ->rules([
                    fn (Get $get): Closure => function (string $attribute, $value, Closure $fail) use ($get) {
                        $u = auth()->user();
                        $cid = $u->isSuperAdmin() ? (int) $get('compagnie_id') : (int) $u->compagnieId();

                        if (PeriodePaie::where('compagnie_id', $cid)->where('annee', (int) $get('annee'))->where('mois', (int) $value)->exists()) {
                            $fail('Cette période existe déjà : ouvrez-la dans la liste.');
                        }
                    },
                ]),
        ])->columns(3);
    }

    public static function infolist(Schema $schema): Schema
    {
        $somme = fn (string $col) => fn (PeriodePaie $r) => number_format((int) $r->bulletins()->visiblePour(auth()->user())->sum($col), 0, ',', ' ').' FCFA';

        return $schema->components([
            Section::make(fn (PeriodePaie $r) => 'Paie de '.$r->libelle())->columns(4)->schema([
                TextEntry::make('statut')->label('État')->badge()->formatStateUsing(fn ($s) => PeriodePaie::STATUTS[$s] ?? $s)
                    ->color(fn ($s) => match ($s) {
                        'brouillon' => 'warning', 'validee' => 'info', default => 'success'
                    }),
                TextEntry::make('nb')->label('Bulletins')->state(fn (PeriodePaie $r) => $r->bulletins()->visiblePour(auth()->user())->count()),
                TextEntry::make('brut')->label('Masse salariale brute')->state($somme('brut')),
                TextEntry::make('net')->label('Net à payer')->state($somme('net_a_payer'))->weight('bold'),
                TextEntry::make('cnss_s')->label('CNSS salariés')->state($somme('cnss_salarie')),
                TextEntry::make('cnss_e')->label('CNSS employeur')->state($somme('cnss_employeur')),
                TextEntry::make('iuts')->label('IUTS à reverser')->state($somme('iuts')),
                TextEntry::make('cout')->label('Coût total employeur')->state($somme('cout_employeur'))->weight('bold'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('annee', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->orderByDesc('mois'))
            ->columns([
                Stack::make([
                    TextColumn::make('annee')->label('Période')->formatStateUsing(fn ($state, PeriodePaie $r) => $r->libelle())->weight('bold')
                        ->description(fn (PeriodePaie $r) => auth()->user()->isSuperAdmin() ? $r->compagnie?->nom : null),
                    TextColumn::make('statut')->label('État')->badge()->formatStateUsing(fn ($s) => PeriodePaie::STATUTS[$s] ?? $s)
                        ->color(fn ($s) => match ($s) {
                            'brouillon' => 'warning', 'validee' => 'info', default => 'success'
                        }),
                    TextColumn::make('nb')->label('Bulletins')
                        ->getStateUsing(fn (PeriodePaie $r) => $r->bulletins()->visiblePour(auth()->user())->count())
                        ->description('Nombre de bulletins'),
                    TextColumn::make('net')->label('Net à payer')->weight('bold')
                        ->getStateUsing(fn (PeriodePaie $r) => number_format((int) $r->bulletins()->visiblePour(auth()->user())->sum('net_a_payer'), 0, ',', ' ').' FCFA'),
                    TextColumn::make('valide_le')->label('Validée le')->dateTime('d/m/Y')->placeholder('Non validée'),
                ])->space(3),
            ])
            ->contentGrid(['md' => 2, 'xl' => 3])
            ->recordUrl(fn (PeriodePaie $r) => static::getUrl('view', ['record' => $r]));
    }

    public static function getRelations(): array
    {
        return [RelationManagers\BulletinsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManagePeriodesPaie::route('/'),
            'view' => Pages\ViewPeriodePaie::route('/{record}'),
        ];
    }
}
