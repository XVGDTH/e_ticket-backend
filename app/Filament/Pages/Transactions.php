<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Gares\GareResource;
use App\Models\Billet;
use App\Models\Compagnie;
use App\Models\Ville;
use App\Services\Finance\ExportFinancier;
use App\Services\Finance\RapportFinancier;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class Transactions extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.transactions';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationLabel = 'Transactions';

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 1;

    private static ?array $villes = null;

    public static function canAccess(): bool
    {
        return RapportFinancier::autorise(auth()->user());
    }

    public function getTitle(): string
    {
        return 'Transactions';
    }

    private static function villes(): array
    {
        return self::$villes ??= Ville::pluck('nom', 'id')->all();
    }

    /** Achats payés, limités au périmètre : tout pour le super admin, sa compagnie (et sa ville) pour l'admin. */
    protected function requete(): Builder
    {
        $user = auth()->user();

        $q = Billet::query()
            ->whereNotNull('date_paiement')
            ->with(['user', 'route.compagnie', 'route.gareDepart.ville']);

        if ($user->isSuperAdmin()) {
            return $q;
        }

        if ($user->isAdminCompagnie()) {
            return $q->whereHas('route', function ($r) use ($user) {
                $r->where('compagnie_id', $user->compagnieId() ?? 0);

                if ($user->villeId()) {
                    $r->whereHas('gareDepart', fn ($g) => $g->where('ville_id', $user->villeId()));
                }
            });
        }

        return $q->whereRaw('1 = 0');
    }

    public function table(Table $table): Table
    {
        $superAdmin = auth()->user()->isSuperAdmin();

        return $table
            ->query(fn (): Builder => $this->requete())
            ->defaultSort('date_paiement', 'desc')
            ->columns([
                TextColumn::make('id')->label('ID')->sortable()->searchable(),
                TextColumn::make('reference')->label('Référence')->searchable()->copyable()->fontFamily('mono'),
                TextColumn::make('client')
                    ->label('Acheteur')
                    ->getStateUsing(fn (Billet $b) => trim(($b->user?->prenom ?? '').' '.($b->user?->nom ?? '')) ?: '—')
                    ->description(fn (Billet $b) => $b->user?->telephone)
                    ->searchable(query: fn (Builder $q, string $s) => $q->whereHas('user', fn ($u) => $u
                        ->where('nom', 'like', "%{$s}%")
                        ->orWhere('prenom', 'like', "%{$s}%")
                        ->orWhere('telephone', 'like', "%{$s}%"))),
                TextColumn::make('compagnie')
                    ->label('Compagnie')
                    ->getStateUsing(fn (Billet $b) => $b->route?->compagnie?->nom)
                    ->badge()->color('info')
                    ->visible($superAdmin),
                TextColumn::make('gare')
                    ->label('Gare de départ')
                    ->getStateUsing(fn (Billet $b) => $b->route?->gareDepart?->quartier)
                    ->description(fn (Billet $b) => $b->route?->gareDepart?->ville?->nom),
                TextColumn::make('trajet')
                    ->label('Trajet')
                    ->getStateUsing(fn (Billet $b) => ($b->route?->gareDepart?->quartier ?? '?').' → '.(self::villes()[$b->route?->ville_arrivee_id] ?? '?'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('date_voyage')->label('Voyage')->date('d/m/Y')
                    ->description(fn (Billet $b) => substr((string) $b->heure, 0, 5))->sortable(),
                TextColumn::make('travelers_count')->label('Voyageurs')->alignCenter()->sortable(),
                TextColumn::make('operateur_paiement')->label('Opérateur')->badge()->color('gray'),
                TextColumn::make('montant_transport')
                    ->label('Prix du billet')
                    ->numeric(0, ',', ' ')->suffix(' FCFA')->alignEnd()->sortable()
                    ->summarize(Sum::make()->label('Total')->numeric(0, ',', ' ')),
                TextColumn::make('taxe')
                    ->label('Frais')
                    ->numeric(0, ',', ' ')->suffix(' FCFA')->alignEnd()->sortable()
                    ->summarize(Sum::make()->label('Total')->numeric(0, ',', ' ')),
                TextColumn::make('total_paye')
                    ->label('Total payé')
                    ->getStateUsing(fn (Billet $b) => (int) $b->montant_transport + (int) $b->taxe)
                    ->numeric(0, ',', ' ')->suffix(' FCFA')->alignEnd()->weight('bold')->color('primary')
                    ->summarize(
                        Summarizer::make()
                            ->label('Total')
                            ->using(fn ($query) => $query->sum(DB::raw('COALESCE(montant_transport, 0) + COALESCE(taxe, 0)')))
                            ->numeric(0, ',', ' ')
                    ),
                TextColumn::make('statut')
                    ->label('Statut')
                    ->badge()
                    ->formatStateUsing(fn ($state) => RapportFinancier::libelleStatut($state))
                    ->color(fn ($state) => match ($state instanceof \BackedEnum ? $state->value : (string) $state) {
                        'utilise' => 'success',
                        'annule' => 'danger',
                        'rembourse' => 'warning',
                        'en_attente_paiement' => 'gray',
                        default => 'info',
                    }),
                TextColumn::make('date_paiement')->label('Payé le')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->filters([
                Filter::make('periode')
                    ->label('Période de paiement')
                    ->schema([
                        DatePicker::make('du')->label('Payé du'),
                        DatePicker::make('au')->label('au'),
                    ])
                    ->query(fn (Builder $q, array $data) => $q
                        ->when($data['du'] ?? null, fn ($q, $d) => $q->whereDate('date_paiement', '>=', $d))
                        ->when($data['au'] ?? null, fn ($q, $d) => $q->whereDate('date_paiement', '<=', $d))),
                SelectFilter::make('compagnie')
                    ->label('Compagnie')
                    ->options(fn () => Compagnie::orderBy('nom')->pluck('nom', 'id')->all())
                    ->visible($superAdmin)
                    ->query(fn (Builder $q, array $data) => filled($data['value'] ?? null)
                        ? $q->whereHas('route', fn ($r) => $r->where('compagnie_id', $data['value']))
                        : $q),
                SelectFilter::make('gare')
                    ->label('Gare')
                    ->options(fn () => GareResource::getEloquentQuery()->with('compagnie')->orderBy('quartier')->get()
                        ->mapWithKeys(fn ($g) => [$g->id => $g->quartier.($superAdmin ? ' · '.($g->compagnie?->nom ?? '') : '')])
                        ->all())
                    ->query(fn (Builder $q, array $data) => filled($data['value'] ?? null)
                        ? $q->whereHas('route', fn ($r) => $r->where('gare_depart_id', $data['value']))
                        : $q),
                SelectFilter::make('statut')
                    ->label('Statut')
                    ->options([
                        'valide' => 'Valide',
                        'utilise' => 'Embarqué',
                        'annule' => 'Annulé',
                        'rembourse' => 'Remboursé',
                    ]),
                SelectFilter::make('operateur_paiement')
                    ->label('Opérateur')
                    ->options(fn () => Billet::query()->whereNotNull('operateur_paiement')->distinct()
                        ->pluck('operateur_paiement', 'operateur_paiement')->all()),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('Aucune transaction')
            ->emptyStateDescription('Aucun achat ne correspond à ces critères.');
    }

    /** Totaux des lignes affichées (filtres et recherche compris), pour la carte d'en-tête. */
    public function totauxFiltres(): object
    {
        return $this->getFilteredSortedTableQuery()
            ->toBase()
            ->reorder()
            ->select([
                DB::raw('COUNT(*) as billets'),
                DB::raw('COALESCE(SUM(travelers_count), 0) as voyageurs'),
                DB::raw('COALESCE(SUM(montant_transport), 0) as prix'),
                DB::raw('COALESCE(SUM(taxe), 0) as frais'),
                DB::raw('COUNT(DISTINCT user_id) as clients'),
            ])
            ->first();
    }

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                Action::make('pdf')->label('PDF')->icon('heroicon-o-document-text')->action(fn () => $this->exporter('pdf')),
                Action::make('xlsx')->label('Excel (.xlsx)')->icon('heroicon-o-table-cells')->action(fn () => $this->exporter('xlsx')),
                Action::make('csv')->label('CSV')->icon('heroicon-o-arrow-down-tray')->action(fn () => $this->exporter('csv')),
            ])
                ->label('Exporter')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('primary')
                ->button(),
        ];
    }

    private static function ligne(Billet $b): array
    {
        $route = $b->route;
        $gare = $route?->gareDepart;

        return [
            (string) $b->id,
            (string) $b->reference,
            $b->date_paiement ? Carbon::parse($b->date_paiement)->format('d/m/Y H:i') : '',
            trim(($b->user?->prenom ?? '').' '.($b->user?->nom ?? '')),
            (string) ($b->user?->telephone ?? ''),
            (string) ($route?->compagnie?->nom ?? ''),
            (string) ($gare?->quartier ?? ''),
            ($gare?->quartier ?? '?').' → '.(self::villes()[$route?->ville_arrivee_id] ?? '?'),
            $b->date_voyage ? Carbon::parse($b->date_voyage)->format('d/m/Y') : '',
            substr((string) $b->heure, 0, 5),
            (int) $b->travelers_count,
            (string) ($b->operateur_paiement ?? '—'),
            RapportFinancier::libelleStatut($b->statut),
            (int) $b->montant_transport,
            (int) $b->taxe,
            (int) $b->montant_transport + (int) $b->taxe,
        ];
    }

    public function exporter(string $format)
    {
        abort_unless(RapportFinancier::autorise(auth()->user()), 403);

        $billets = $this->getFilteredSortedTableQuery()->limit(20000)->get();
        $t = $this->totauxFiltres();
        $fmt = fn ($n) => number_format((float) $n, 0, ',', ' ');

        $kpis = [
            ['Total payé', $fmt($t->prix + $t->frais).' FCFA'],
            ['Prix des billets', $fmt($t->prix).' FCFA'],
            ['Frais', $fmt($t->frais).' FCFA'],
            ['Transactions', $fmt($t->billets)],
            ['Voyageurs', $fmt($t->voyageurs)],
            ['Acheteurs distincts', $fmt($t->clients)],
        ];

        $meta = [
            'Lignes exportées' => $fmt($billets->count()),
            'Généré le' => now()->format('d/m/Y H:i'),
            'Généré par' => auth()->user()->getFilamentName(),
        ];

        return ExportFinancier::exporter(
            $format,
            'transactions-'.now()->format('Ymd-His'),
            'Transactions des clients de l\'application',
            $meta,
            $kpis,
            [[
                'titre' => 'Transactions',
                'entetes' => RapportFinancier::ENTETES_TRANSACTIONS,
                'lignes' => $billets->map(fn (Billet $b) => self::ligne($b))->all(),
            ]],
        );
    }
}
