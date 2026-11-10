<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\ChoisitCompagnie;
use App\Models\CgBudget;
use App\Models\Compagnie;
use App\Models\RouteTransport;
use App\Services\ControleGestion\Acces;
use App\Services\ControleGestion\BudgetService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

class Budget extends Page
{
    use ChoisitCompagnie;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationLabel = 'Budget';

    protected static string|UnitEnum|null $navigationGroup = 'Contrôle de gestion';

    protected static ?int $navigationSort = 4;

    protected static ?string $title = 'Budget';

    protected static ?string $slug = 'budget';

    protected string $view = 'filament.pages.budget';

    public string $annee = '';

    public string $budgetId = '';

    public static function canAccess(): bool
    {
        return Acces::finance(auth()->user());
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Construit à partir du plan de transport, mensualisé, avec des versions datées';
    }

    public function mount(): void
    {
        $this->initCompagnie();
        $this->annee = (string) today()->year;
    }

    public function choisirBudget(int $id): void
    {
        $budget = CgBudget::query()->whereIn('compagnie_id', $this->cids())->find($id);

        if ($budget) {
            if (auth()->user()->isSuperAdmin()) {
                $this->compagnieId = (string) $budget->compagnie_id;
            }

            $this->budgetId = (string) $id;
        }
    }

    protected function getViewData(): array
    {
        $cid = $this->cid();

        $budgets = CgBudget::query()
            ->whereIn('compagnie_id', $this->cids())
            ->where('annee', (int) $this->annee)
            ->orderByDesc('version')
            ->get();

        $choisi = $this->toutesCompagnies()
            ? null
            : ($budgets->firstWhere('id', (int) $this->budgetId) ?? $budgets->first());
        $mensuel = [];
        $parRoute = [];
        $total = null;

        if ($choisi) {
            $lignes = $choisi->lignes()->get();
            $couts = ['commissions', 'carburant', 'equipage', 'maintenance', 'pneus', 'frais_route', 'fixes_car', 'gare', 'siege'];
            $somme = fn ($g) => [
                'departs' => (int) $g->sum('departs'),
                'km' => (int) $g->sum('km'),
                'recettes' => (int) $g->sum('recettes'),
                'couts' => (int) collect($couts)->sum(fn ($c) => $g->sum($c)),
            ];

            $mensuel = $lignes->groupBy('mois')->map($somme)->map(fn ($m) => $m + ['resultat' => $m['recettes'] - $m['couts']])->sortKeys()->all();
            $total = $somme($lignes);
            $total['resultat'] = $total['recettes'] - $total['couts'];

            $routes = RouteTransport::with(['gareDepart', 'villeArrivee'])->whereIn('id', $lignes->pluck('route_transport_id')->unique())->get()->keyBy('id');

            $parRoute = $lignes->groupBy('route_transport_id')->map(function ($g, $id) use ($somme, $routes) {
                $m = $somme($g);
                $r = $routes->get($id);

                return $m + [
                    'libelle' => ($r?->gareDepart?->quartier ?? '?').' → '.($r?->villeArrivee?->nom ?? '?'),
                    'resultat' => $m['recettes'] - $m['couts'],
                ];
            })->sortByDesc('resultat')->values()->all();
        }

        return [
            'compagnies' => $this->compagniesChoix(),
            'compagnieNoms' => Compagnie::query()->whereIn('id', $this->cids())->pluck('nom', 'id'),
            'annees' => range(today()->year - 1, today()->year + 1),
            'budgets' => $budgets,
            'choisi' => $choisi,
            'mensuel' => $mensuel,
            'parRoute' => $parRoute,
            'total' => $total,
            'mois_noms' => $this->nomsMois(),
        ];
    }

    // ------------------------------------------------------------------ actions

    protected function getHeaderActions(): array
    {
        return [
            Action::make('creer')
                ->label('Nouveau budget')
                ->icon('heroicon-o-plus-circle')
                ->modalHeading('Nouvelle version du budget')
                ->modalDescription('Calculé avec vos lignes actives, horaires, jours de départ, distances, tarifs et nombre de places. Les standards sont préremplis avec vos 3 derniers mois réels : corrigez-les si besoin.')
                ->modalWidth('3xl')
                ->fillForm(fn () => ['annee' => (int) $this->annee ?: today()->year, 'libelle' => 'Budget '.($this->annee ?: today()->year)]
                    + BudgetService::standardsHistoriques($this->cid()))
                ->schema(fn (): array => [
                    Grid::make(2)->schema([
                        Select::make('annee')->label('Année')
                            ->options(collect(range(today()->year - 1, today()->year + 1))->mapWithKeys(fn ($y) => [$y => $y])->all())->required(),
                        TextInput::make('libelle')->label('Libellé')->required()->maxLength(120),
                        TextInput::make('remplissage_cible_pct')->label('Remplissage cible')->numeric()->minValue(1)->maxValue(100)->step('any')->required()->suffix('%'),
                        TextInput::make('cout_horaire_equipage')->label('Coût horaire de l’équipage')->numeric()->minValue(0)->required()->suffix('FCFA/h'),
                        TextInput::make('cout_km_maintenance')->label('Maintenance au kilomètre')->numeric()->minValue(0)->required()->suffix('FCFA/km'),
                        TextInput::make('cout_km_pneus')->label('Pneumatiques au kilomètre')->numeric()->minValue(0)->required()->suffix('FCFA/km'),
                        TextInput::make('frais_route_depart')->label('Péages et frais de route par départ')->numeric()->minValue(0)->required()->suffix('FCFA'),
                        TextInput::make('fixes_car_mensuel')->label('Coûts fixes des cars par mois')->numeric()->minValue(0)->required()->suffix('FCFA')
                            ->helperText('Amortissement, assurance, visite technique, taxes.'),
                        Textarea::make('note')->label('Note')->rows(2)->maxLength(300)->columnSpanFull(),
                    ]),
                ])
                ->action(function (array $data) {
                    abort_unless(Acces::finance(auth()->user()) && $this->cid() > 0, 403);
                    abort_unless(abs((int) $data['annee'] - today()->year) <= 1, 403);

                    $r = BudgetService::generer($this->cid(), $data, auth()->user());

                    $this->annee = (string) $r['budget']->annee;
                    $this->budgetId = (string) $r['budget']->id;

                    $alertes = collect([
                        $r['sans_distance'] ? 'Sans distance (km, carburant, maintenance à zéro) : '.implode(' ; ', array_slice($r['sans_distance'], 0, 3)).'.' : null,
                        $r['sans_duree'] ? 'Sans durée (équipage à zéro) : '.implode(' ; ', array_slice($r['sans_duree'], 0, 3)).'.' : null,
                    ])->filter();

                    $n = Notification::make()
                        ->title('Budget enregistré en brouillon (version '.$r['budget']->version.')')
                        ->body($alertes->implode(' '));

                    $alertes->isNotEmpty() ? $n->warning() : $n->success();
                    $n->send();
                })
                ->visible(fn () => $this->cid() > 0),
        ];
    }

    protected function budget(int $id): CgBudget
    {
        abort_unless(Acces::finance(auth()->user()), 403);

        return CgBudget::where('compagnie_id', $this->cid())->findOrFail($id);
    }

    public function validerAction(): Action
    {
        return Action::make('valider')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Valider ce budget ?')
            ->modalDescription('Une version validée ne peut plus être modifiée : elle devient la référence des écarts. Pour changer d’hypothèse, créez une nouvelle version.')
            ->action(function (array $arguments) {
                $b = $this->budget((int) ($arguments['id'] ?? 0));

                if ($b->estValide()) {
                    return;
                }

                $b->update(['statut' => 'valide', 'valide_le' => now(), 'valide_par' => auth()->id()]);

                Notification::make()->title('Budget validé : version '.$b->version)->success()->send();
            });
    }

    public function supprimerAction(): Action
    {
        return Action::make('supprimer')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Supprimer ce brouillon ?')
            ->action(function (array $arguments) {
                $b = $this->budget((int) ($arguments['id'] ?? 0));

                if ($b->estValide()) {
                    Notification::make()->title('Une version validée ne se supprime pas')->danger()->send();

                    return;
                }

                $b->delete();
                $this->budgetId = '';

                Notification::make()->title('Brouillon supprimé')->success()->send();
            });
    }
}
