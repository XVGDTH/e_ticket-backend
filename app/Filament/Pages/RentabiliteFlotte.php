<?php

namespace App\Filament\Pages;

use App\Models\Compagnie;
use App\Models\Vehicule;
use App\Services\Finance\ExportFinancier;
use App\Services\Flotte\Acces;
use App\Services\Flotte\Alertes;
use App\Services\Flotte\Rentabilite;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Pages\Page;
use Livewire\Attributes\Url;
use UnitEnum;

class RentabiliteFlotte extends Page
{
    protected string $view = 'filament.pages.rentabilite-flotte';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Rentabilité des cars';

    protected static string|UnitEnum|null $navigationGroup = 'Flotte';

    protected static ?int $navigationSort = 5;

    public string $compagnieId = '';

    public string $periode = 'mois';

    public string $du = '';

    public string $au = '';

    #[Url]
    public string $vehiculeId = '';

    public static function canAccess(): bool
    {
        return Acces::finance(auth()->user());
    }

    public function getTitle(): string
    {
        return 'Rentabilité des cars';
    }

    public function mount(): void
    {
        $u = auth()->user();

        $this->compagnieId = $u->isSuperAdmin()
            ? ''
            : (string) $u->compagnieId();

        $this->du = today()->startOfMonth()->toDateString();
        $this->au = today()->toDateString();
    }

    public function updatedCompagnieId(): void
    {
        $this->vehiculeId = '';
    }

    protected function cid(): int
    {
        $u = auth()->user();

        return $u->isSuperAdmin() ? (int) $this->compagnieId : (int) $u->compagnieId();
    }

    private function date(string $v): ?Carbon
    {
        try {
            return $v !== '' ? Carbon::parse($v)->startOfDay() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array{0: Carbon, 1: Carbon} */
    protected function bornes(): array
    {
        $auj = today();

        return match ($this->periode) {
            '30j' => [$auj->copy()->subDays(29), $auj],
            'mois_dernier' => [$auj->copy()->subMonthNoOverflow()->startOfMonth(), $auj->copy()->subMonthNoOverflow()->endOfMonth()->startOfDay()],
            'trimestre' => [$auj->copy()->startOfQuarter(), $auj],
            'annee' => [$auj->copy()->startOfYear(), $auj],
            'perso' => [$this->date($this->du) ?? $auj->copy()->startOfMonth(), $this->date($this->au) ?? $auj],
            default => [$auj->copy()->startOfMonth(), $auj],
        };
    }

    public function donnees(): array
    {
        $u = auth()->user();
        $cid = $this->cid();
        [$debut, $fin] = $this->bornes();
        $compagnies = $u->isSuperAdmin()
            ? Compagnie::query()->where('actif', true)->orderBy('nom')->pluck('nom', 'id')->all()
            : [];
        $idsCompagnies = $u->isSuperAdmin()
            ? ($cid > 0 && array_key_exists($cid, $compagnies) ? [$cid] : array_map('intval', array_keys($compagnies)))
            : [$cid];
        $nomsCompagnies = array_map('strval', $compagnies);
        $cars = [];
        $nonRattache = ['montant' => 0, 'departs' => 0];

        foreach ($idsCompagnies as $compagnieId) {
            $idsVehicules = Vehicule::query()
                ->visiblePour($u)
                ->where('compagnie_id', $compagnieId)
                ->pluck('id')
                ->all();
            $resultat = Rentabilite::analyser($compagnieId, $debut, $fin, $idsVehicules);
            $nom = $nomsCompagnies[$compagnieId] ?? Compagnie::query()->whereKey($compagnieId)->value('nom') ?? 'Compagnie';

            foreach ($resultat['cars'] as $car) {
                $car['compagnie'] = $nom;
                $car['libelle'] = $nom.' · '.$car['libelle'];
                $cars[] = $car;
            }

            $nonRattache['montant'] += $resultat['non_rattache']['montant'];
            $nonRattache['departs'] += $resultat['non_rattache']['departs'];
        }

        usort($cars, fn (array $a, array $b): int => $b['resultat'] <=> $a['resultat']);

        $placesOffertes = (int) collect($cars)->sum('places_offertes');
        $passagers = (int) collect($cars)->sum('pax');
        $recettes = (int) collect($cars)->sum('recettes');
        $charges = (int) collect($cars)->sum('charges');
        $amortissement = (int) collect($cars)->sum('amortissement');
        $kilometres = (int) collect($cars)->sum('km');
        $carsAvecConsommation = collect($cars)->filter(
            fn (array $car): bool => $car['km'] >= (int) config('flotte.km_min_consommation', 300) && $car['litres'] > 0
        );
        $kilometresConsommation = (int) $carsAvecConsommation->sum('km');
        $litresConsommation = (float) $carsAvecConsommation->sum('litres');
        $resultatFlotte = $recettes - $charges - $amortissement;
        $flotte = [
            'nb_cars' => count($cars),
            'voyages' => (int) collect($cars)->sum('voyages'),
            'voyages_moyen' => count($cars) > 0 ? round(collect($cars)->sum('voyages') / count($cars), 1) : 0,
            'pax' => $passagers,
            'km' => $kilometres,
            'recettes' => $recettes,
            'charges' => $charges,
            'amortissement' => $amortissement,
            'resultat' => $resultatFlotte,
            'marge' => $recettes > 0 ? round($resultatFlotte / $recettes * 100, 1) : null,
            'remplissage' => $placesOffertes > 0 ? round($passagers / $placesOffertes * 100, 1) : null,
            'l100' => $kilometresConsommation > 0 ? round($litresConsommation / $kilometresConsommation * 100, 1) : null,
            'rentables' => collect($cars)->where('verdict', 'rentable')->count(),
            'fragiles' => collect($cars)->where('verdict', 'fragile')->count(),
            'deficitaires' => collect($cars)->where('verdict', 'deficitaire')->count(),
        ];
        $choisi = $this->vehiculeId !== '' ? collect($cars)->firstWhere('id', (int) $this->vehiculeId) : null;
        $alertes = collect(Alertes::pour($u))
            ->when($cid > 0, fn ($items) => $items->where('compagnie_id', $cid))
            ->map(function (array $alerte) use ($nomsCompagnies): array {
                $alerte['objet'] = ($nomsCompagnies[$alerte['compagnie_id']] ?? 'Compagnie').' · '.$alerte['objet'];

                return $alerte;
            })
            ->values()
            ->all();

        return [
            'compagnies' => $u->isSuperAdmin() ? ['' => 'Toutes les compagnies'] + $compagnies : [],
            'nomCompagnie' => $cid > 0 ? ($nomsCompagnies[$cid] ?? '—') : 'Toutes les compagnies',
            'debut' => $debut,
            'fin' => $fin,
            'cars' => $cars,
            'flotte' => $flotte,
            'non_rattache' => $nonRattache,
            'choisi' => $choisi,
            'alertes' => $alertes,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                Action::make('pdf')->label('PDF')->icon('heroicon-o-document-text')->action(fn () => $this->exporter('pdf')),
                Action::make('xlsx')->label('Excel (.xlsx)')->icon('heroicon-o-table-cells')->action(fn () => $this->exporter('xlsx')),
                Action::make('csv')->label('CSV')->icon('heroicon-o-arrow-down-tray')->action(fn () => $this->exporter('csv')),
            ])->label('Exporter')->icon('heroicon-o-arrow-down-tray')->color('primary')->button(),
        ];
    }

    public function exporter(string $format)
    {
        abort_unless(static::canAccess(), 403);

        $d = $this->donnees();
        $fmt = fn ($n) => number_format((float) $n, 0, ',', ' ').' FCFA';
        $f = $d['flotte'];

        $sections = [[
            'titre' => 'Classement des cars',
            'entetes' => ['Car', 'Voyages', 'Passagers', 'Remplissage %', 'Recettes', 'Dépenses', 'Amortissement', 'Résultat', 'Marge %', 'Verdict'],
            'lignes' => collect($d['cars'])->map(fn ($c) => [
                $c['libelle'], $c['voyages'], $c['pax'], $c['remplissage'] ?? '', $c['recettes'], $c['charges'], $c['amortissement'], $c['resultat'], $c['marge'] ?? '', Rentabilite::verdictLibelle($c['verdict']),
            ])->all(),
        ]];

        if ($c = $d['choisi']) {
            $sections[] = [
                'titre' => 'Détail : '.$c['libelle'],
                'entetes' => ['Indicateur', 'Valeur'],
                'lignes' => [
                    ['Recettes de l\'application', $c['recettes_app']],
                    ['Recettes du guichet', $c['recettes_guichet']],
                    ['Recettes des colis', $c['recettes_colis']],
                    ['Dépenses', $c['charges']],
                    ['Amortissement', $c['amortissement']],
                    ['Résultat avant amortissement', $c['resultat_avant_amort']],
                    ['Résultat', $c['resultat']],
                    ['Kilomètres', $c['km']],
                    ['Recette par km', $c['recette_km'] ?? ''],
                    ['Coût par km', $c['cout_km'] ?? ''],
                    ['Consommation L/100 km', $c['l100'] ?? ''],
                    ['Seuil de rentabilité (passagers par voyage)', $c['seuil_pax'] ?? ''],
                    ['Valeur nette comptable', $c['vnc']],
                ],
            ];

            $sections[] = [
                'titre' => 'Mesures à prendre : '.$c['libelle'],
                'entetes' => ['Niveau', 'Sujet', 'Détail'],
                'lignes' => collect($c['conseils'])->map(fn ($x) => [$x['niveau'], $x['titre'], $x['texte']])->all(),
            ];
        }

        return ExportFinancier::exporter(
            $format,
            'rentabilite-cars-'.now()->format('Ymd-His'),
            'Rentabilité des cars : '.$d['nomCompagnie'],
            [
                'Compagnie' => $d['nomCompagnie'],
                'Période' => $d['debut']->format('d/m/Y').' au '.$d['fin']->format('d/m/Y'),
                'Base' => 'Billets de l\'application, ventes du guichet et colis saisis, dépenses et amortissement saisis',
                'Généré le' => now()->format('d/m/Y H:i'),
                'Généré par' => auth()->user()->getFilamentName(),
            ],
            [
                ['Recettes', $fmt($f['recettes'])],
                ['Dépenses', $fmt($f['charges'])],
                ['Amortissement', $fmt($f['amortissement'])],
                ['Résultat de la flotte', $fmt($f['resultat'])],
            ],
            $sections,
        );
    }
}
