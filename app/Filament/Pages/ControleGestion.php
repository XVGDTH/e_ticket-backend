<?php

namespace App\Filament\Pages;

use App\Models\ChargeStructure;
use App\Models\Compagnie;
use App\Services\ControleGestion\Acces;
use App\Services\ControleGestion\ModeleCouts;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

class ControleGestion extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationLabel = 'Tableau de bord';

    protected static string|UnitEnum|null $navigationGroup = 'Contrôle de gestion';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Contrôle de gestion';

    protected static ?string $slug = 'controle-de-gestion';

    protected string $view = 'filament.pages.controle-gestion';

    public string $periode = 'mois';

    public ?string $du = null;

    public ?string $au = null;

    /** Onglet des cartes : cars, lignes, gares, activites. */
    public string $vue = 'cars';

    /** Compagnie analysée (super admin uniquement ; les autres sont fixés à leur compagnie). */
    public string $compagnieId = '';

    protected ?array $cache = null;

    public static function canAccess(): bool
    {
        return Acces::visible(auth()->user());
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Rentabilité par départ, car, ligne, gare et activité';
    }

    public function mount(): void
    {
        if (auth()->user()->isSuperAdmin()) {
            $this->compagnieId = '';
        }
    }

    public function updatedCompagnieId(): void
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        if ($this->compagnieId !== '') {
            abort_unless(Compagnie::query()->whereKey((int) $this->compagnieId)->exists(), 404);
        }

        $this->cache = null;
    }

    public function updatedPeriode(): void
    {
        $this->cache = null;
    }

    public function updatedDu(): void
    {
        $this->cache = null;
    }

    public function updatedAu(): void
    {
        $this->cache = null;
    }

    protected function getViewData(): array
    {
        return ['d' => $this->donnees()];
    }

    public function choisirVue(string $vue): void
    {
        if (in_array($vue, ['cars', 'lignes', 'gares', 'activites'], true)) {
            $this->vue = $vue;
        }
    }

    public function supprimerCharge(int $id): void
    {
        abort_unless(static::canAccess(), 403);

        $charge = ChargeStructure::query()->visiblePour(auth()->user())->whereKey($id)->first();

        if ($charge) {
            $charge->delete();
            Notification::make()->title('Charge supprimée')->success()->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('charges')
                ->label('Ajouter une charge')
                ->icon('heroicon-o-plus-circle')
                ->color('gray')
                ->url(fn () => ChargesStructure::getUrl()),

            Action::make('parametres')
                ->label('Paramètres des coûts')
                ->icon('heroicon-o-cog-6-tooth')
                ->color('gray')
                ->url(fn () => ParametresCouts::getUrl())
                ->visible(fn () => Acces::finance(auth()->user())),
        ];
    }

    protected function cid(): int
    {
        $u = auth()->user();

        return $u->isSuperAdmin() && $this->compagnieId !== ''
            ? (int) $this->compagnieId
            : ($u->isSuperAdmin() ? 0 : (int) ($u->compagnieId() ?? 0));
    }

    /** @return array{0: Carbon, 1: Carbon} */
    protected function bornes(): array
    {
        $auj = today();

        [$debut, $fin] = match ($this->periode) {
            'mois_dernier' => [$auj->copy()->subMonthNoOverflow()->startOfMonth(), $auj->copy()->subMonthNoOverflow()->endOfMonth()],
            '7j' => [$auj->copy()->subDays(6), $auj->copy()],
            '30j' => [$auj->copy()->subDays(29), $auj->copy()],
            'annee' => [$auj->copy()->startOfYear(), $auj->copy()],
            'perso' => $this->bornesPerso(),
            default => [$auj->copy()->startOfMonth(), $auj->copy()],
        };

        // Une année au maximum, pour garder la page rapide.
        if ($debut->diffInDays($fin) > 366) {
            $debut = $fin->copy()->subDays(365);
        }

        return [$debut, $fin];
    }

    protected function bornesPerso(): array
    {
        try {
            $d = $this->du ? Carbon::parse($this->du)->startOfDay() : today()->startOfMonth();
            $f = $this->au ? Carbon::parse($this->au)->startOfDay() : today()->copy();
        } catch (\Throwable) {
            return [today()->startOfMonth(), today()->copy()];
        }

        return $d->gt($f) ? [$f, $d] : [$d, $f];
    }

    protected function donnees(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $u = auth()->user();
        $cid = $this->cid();
        [$debut, $fin] = $this->bornes();

        if ($u->isSuperAdmin() && $this->compagnieId === '') {
            $compagnies = Compagnie::query()->orderBy('nom')->get(['id', 'nom']);
            $parCompagnie = $compagnies->map(function (Compagnie $compagnie) use ($debut, $fin, $u): array {
                return [
                    'id' => (int) $compagnie->id,
                    'nom' => $compagnie->nom,
                    'analyse' => ModeleCouts::analyser((int) $compagnie->id, $debut, $fin, $u),
                ];
            });

            return $this->cache = [
                'compagnies' => collect(['' => 'Toutes les compagnies'])->union(Compagnie::query()->orderBy('nom')->pluck('nom', 'id')),
                'parCompagnie' => $parCompagnie,
                'a' => null,
                'compagnie' => null,
                'alertes' => [],
                'charges' => collect(),
                'periodes' => [
                    'mois' => 'Ce mois',
                    'mois_dernier' => 'Mois dernier',
                    '7j' => '7 derniers jours',
                    '30j' => '30 derniers jours',
                    'annee' => 'Cette année',
                    'perso' => 'Dates personnalisées',
                ],
            ];
        }

        $a = $cid > 0 ? ModeleCouts::analyser($cid, $debut, $fin, $u) : null;

        $charges = $cid > 0
            ? ChargeStructure::query()->visiblePour($u)->where('compagnie_id', $cid)
                ->whereDate('periode_debut', '<=', $fin->toDateString())
                ->whereDate('periode_fin', '>=', $debut->toDateString())
                ->with('gare')->orderBy('niveau')->orderByDesc('montant')->get()
            : collect();

        return $this->cache = [
            'compagnies' => $u->isSuperAdmin()
                ? collect(['' => 'Toutes les compagnies'])->union(Compagnie::query()->orderBy('nom')->pluck('nom', 'id'))
                : collect(),
            'parCompagnie' => collect(),
            'compagnie' => $cid > 0 ? Compagnie::whereKey($cid)->value('nom') : null,
            'a' => $a,
            'alertes' => $a ? $this->alertes($a) : [],
            'charges' => $charges,
            'periodes' => [
                'mois' => 'Ce mois',
                'mois_dernier' => 'Mois dernier',
                '7j' => '7 derniers jours',
                '30j' => '30 derniers jours',
                'annee' => 'Cette année',
                'perso' => 'Dates personnalisées',
            ],
        ];
    }

    /** Points d'attention, du plus urgent au moins urgent. */
    protected function alertes(array $a): array
    {
        $n = fn ($v) => number_format((int) $v, 0, ',', ' ');
        $q = $a['qualite'];
        $out = [];

        if ((int) $a['total']['voyages'] === 0) {
            return $out;
        }

        $pertes = collect($a['par_ligne'])->filter(fn ($l) => $l['resultat'] < 0)->sortBy('resultat');

        if ($pertes->isNotEmpty()) {
            $pire = $pertes->first();
            $out[] = ['danger', $pertes->count().' ligne(s) en perte', 'La plus lourde : '.$pire['libelle'].' ('.$n($pire['resultat']).' FCFA). Revoyez ses horaires, son tarif ou le car affecté.'];
        }

        $ec = $a['ecart_carburant'];

        if ($ec['standard'] > 0 && abs($ec['ecart'] / $ec['standard'] * 100) > $a['parametres']['ecart_seuil_pct']) {
            $out[] = ['danger', 'Écart carburant au-dessus du seuil', 'Le réel s’écarte de '.number_format($ec['ecart'] / $ec['standard'] * 100, 1, ',', ' ').' % du coût standard (seuil '.$a['parametres']['ecart_seuil_pct'].' %). Vérifiez les justificatifs des pleins et la conduite.'];
        }

        if ($q['voyages_a_cloturer'] > 0) {
            $out[] = ['warning', $q['voyages_a_cloturer'].' voyage(s) à clôturer', 'Leurs kilomètres, le carburant et les ventes du guichet ne sont pas saisis : les résultats sont provisoires (coûts standards).'];
        }

        if ($q['routes_sans_distance'] !== []) {
            $out[] = ['warning', count($q['routes_sans_distance']).' ligne(s) sans distance', implode(' ; ', array_slice($q['routes_sans_distance'], 0, 3)).'. Sans distance, sièges-km, passagers-km et carburant standard restent à zéro : renseignez-la dans Transport, Routes.'];
        }

        if ($q['lignes_sans_duree'] !== []) {
            $out[] = ['warning', count($q['lignes_sans_duree']).' ligne(s) sans durée', implode(' ; ', array_slice($q['lignes_sans_duree'], 0, 3)).'. La durée (par exemple 5h30), à renseigner dans Transport, Routes, sert à calculer le coût horaire de l’équipage.'];
        }

        if ($q['chauffeurs_sans_paie'] !== []) {
            $out[] = ['warning', 'Équipage non chiffré par la paie', implode(' ; ', array_slice($q['chauffeurs_sans_paie'], 0, 3)).' : fiche Personnel non liée au chauffeur, ou aucun bulletin de paie. Liez le chauffeur à sa fiche et générez sa paie.'];
        }

        if ($q['voyages_sans_chauffeur'] > 0) {
            $out[] = ['info', $q['voyages_sans_chauffeur'].' voyage(s) sans chauffeur', 'Leur équipage ne peut pas être chiffré : renseignez le chauffeur sur le voyage.'];
        }

        $vides = collect($a['par_car'])->filter(fn ($c) => $c['remplissage'] !== null && $c['remplissage'] < 50);

        if ($vides->isNotEmpty()) {
            $out[] = ['warning', $vides->count().' car(s) sous 50 % de remplissage', $vides->pluck('libelle')->take(3)->implode(' ; ').'. Regroupez des horaires ou affectez un car plus petit.'];
        }

        if ($q['cars_sans_amortissement'] !== []) {
            $out[] = ['info', 'Amortissement non compté', implode(' ; ', array_slice($q['cars_sans_amortissement'], 0, 3)).' : prix, date et durée d’amortissement manquent sur la fiche du car.'];
        }

        if ($q['equipage_ignore'] > 0) {
            $out[] = ['info', $n($q['equipage_ignore']).' FCFA de dépenses « Chauffeur » ignorées', 'Elles ne sont rattachées à aucun voyage et la paie couvre déjà l’équipage de ce car : les compter ferait un doublon.'];
        }

        if ($a['hors_departs'] && array_sum($a['hors_departs']['postes']) > 0) {
            $out[] = ['info', $n(array_sum($a['hors_departs']['postes'])).' FCFA hors départs', 'Ces dépenses concernent des cars sans voyage sur la période : elles ne sont rattachées à aucun départ.'];
        }

        $ordre = ['danger' => 0, 'warning' => 1, 'info' => 2];
        usort($out, fn ($x, $y) => $ordre[$x[0]] <=> $ordre[$y[0]]);

        return $out;
    }
}
