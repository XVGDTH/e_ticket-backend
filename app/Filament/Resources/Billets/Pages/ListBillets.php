<?php

namespace App\Filament\Resources\Billets\Pages;

use App\Enums\BilletStatut;
use App\Filament\Resources\Billets\BilletResource;
use App\Models\Billet;
use App\Models\Gare;
use App\Models\Horaire;
use App\Models\ReportBillet;
use App\Models\RouteTransport;
use App\Models\Ville;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

class ListBillets extends ListRecords
{
    protected static string $resource = BilletResource::class;

    /** Même format que horaires.jours_semaine (Carbon::dayOfWeek : 0 = dimanche). */
    private const JOURS = ['dim', 'lun', 'mar', 'mer', 'jeu', 'ven', 'sam'];

    /** Un départ reste « en cours » jusqu'à HH:MM + cette durée (en minutes). */
    private const DELAI_MINUTES = 1;

    private const VUES = [
        'prets' => ['label' => 'Prêts à embarquer', 'couleur' => '#22c55e', 'statuts' => ['valide']],
        'embarques' => ['label' => 'Embarqués', 'couleur' => '#0ea5e9', 'statuts' => ['utilise']],
        'attente' => ['label' => 'En attente de paiement', 'couleur' => '#f59e0b', 'statuts' => ['en_attente_paiement']],
        'annules' => ['label' => 'Annulés / remboursés', 'couleur' => '#ef4444', 'statuts' => ['annule', 'rembourse']],
        'reportes' => ['label' => 'Reportés', 'couleur' => '#a855f7', 'statuts' => []],
    ];

    /** Ce que le tableau affiche (clé de VUES). */
    public string $vue = 'prets';

    /** Trajet sur lequel on zoome dans le créneau ; null = tous les trajets du créneau. */
    public ?int $routeChoisie = null;

    /** Créneau épinglé à la main (« Y-m-d|HH:MM ») ; null = suivi automatique de l'heure. */
    public ?string $creneauManuel = null;

    /** Vrai = le tableau montre tous les billets (tous jours, tous voyages). */
    public bool $tousLesBillets = false;

    /** Filtres rapides (chaînes vides = pas de filtre). */
    public string $compagnieId = '';

    public string $villeId = '';

    protected ?array $contexteCache = null;

    protected ?int $nbAEmbarquerCache = null;

    // ---------- Actions de l'en-tête (Livewire) ----------

    public function choisirVue(string $vue): void
    {
        if (array_key_exists($vue, self::VUES)) {
            $this->vue = $vue;
            $this->resetPage();
        }
    }

    public function choisirRoute(?int $routeId = null): void
    {
        $this->routeChoisie = $routeId;
        $this->resetPage();
    }

    public function choisirCreneau(string $cle): void
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}\|\d{2}:\d{2}$/', $cle)) {
            return;
        }

        // Cliquer sur le départ déjà suivi automatiquement ne doit pas figer la page.
        if (! $this->creneauManuel) {
            $c = $this->contexte();

            if ($c['creneau'] && $cle === $c['jour']->toDateString().'|'.$c['creneau']) {
                return;
            }
        }

        $this->creneauManuel = $cle;
        $this->routeChoisie = null;
        $this->resetPage();
    }

    public function suivreAuto(): void
    {
        $this->creneauManuel = null;
        $this->routeChoisie = null;
        $this->resetPage();
    }

    public function basculerTousLesBillets(): void
    {
        $this->tousLesBillets = ! $this->tousLesBillets;
        $this->resetPage();
    }

    public function reinitialiserFiltres(): void
    {
        $this->compagnieId = '';
        $this->villeId = '';
        $this->routeChoisie = null;
        $this->resetPage();
    }

    /** Livewire : appelé quand le sélecteur de compagnie change. */
    public function updatedCompagnieId(): void
    {
        // La ville choisie n'a peut-être aucune gare pour cette compagnie.
        if (filled($this->villeId) && ! $this->optionsFiltres()['villes']->has((int) $this->villeId)) {
            $this->villeId = '';
        }

        $this->routeChoisie = null;
        $this->resetPage();
    }

    /** Livewire : appelé quand le sélecteur de ville change. */
    public function updatedVilleId(): void
    {
        $this->routeChoisie = null;
        $this->resetPage();
    }

    // ---------- Appelé par BilletResource::table() ----------

    /** Ne garde dans le tableau que les billets du voyage affiché. */
    public function restreindre(Builder $query): Builder
    {
        if (filled($this->compagnieId) || filled($this->villeId)) {
            $query->whereIn('billets.gare_id', $this->garesFiltrees()->pluck('id'));
        }

        if ($this->tousLesBillets) {
            return $query;
        }

        $c = $this->contexte();

        if (! $c['creneau']) {
            return $query->whereRaw('1 = 0');
        }

        // Billets reportés : ceux qui ont quitté ce départ pour un autre jour.
        if ($this->vueActive() === 'reportes') {
            return $query->whereIn('billets.id', ReportBillet::query()
                ->whereDate('ancienne_date', $c['jour'])
                ->where('ancienne_heure', $c['creneau'])
                ->when($c['route'], fn ($q) => $q->where('route_transport_id', $c['route']))
                ->select('billet_id'));
        }

        $query
            ->whereDate('billets.date_voyage', $c['jour'])
            ->whereRaw('LEFT(billets.heure, 5) = ?', [$c['creneau']])
            ->whereIn('billets.statut', self::VUES[$this->vueActive()]['statuts']);

        if ($c['route']) {
            $query->where('billets.route_transport_id', $c['route']);
        }

        return $query;
    }

    /** Données de l'en-tête (vue entete-voyage.blade.php). */
    public function donneesEntete(): array
    {
        $c = $this->contexte();
        $jour = $c['jour'];
        $creneau = $c['creneau'];

        $trajets = $creneau ? $c['voyages']->where('hhmm', $creneau)->values() : collect();
        $selection = $c['route'] ? $trajets->where('route_id', $c['route']) : $trajets;

        $vues = [];

        foreach (self::VUES as $cle => $definition) {
            $vues[$cle] = [
                'label' => $definition['label'],
                'couleur' => $definition['couleur'],
                'total' => (int) $selection->sum($cle),
                'active' => $this->vueActive() === $cle,
            ];
        }

        $depart = $creneau ? $jour->copy()->setTimeFromTimeString($creneau) : null;

        $strip = $c['creneaux']->map(function (string $hhmm) use ($c, $jour) {
            $fin = $jour->copy()->setTimeFromTimeString($hhmm)->addMinutes(self::DELAI_MINUTES);

            return [
                'cle' => $jour->toDateString().'|'.$hhmm,
                'label' => str_replace(':', 'h', $hhmm),
                'prets' => (int) $c['voyages']->where('hhmm', $hhmm)->sum('prets'),
                'passe' => $fin->lessThanOrEqualTo($c['maintenant']),
                'actif' => $hhmm === $c['creneau'],
            ];
        })->all();

        return [
            'jour' => $jour,
            'estAujourdhui' => $jour->isToday(),
            'creneau' => $creneau,
            'label' => $creneau ? str_replace(':', 'h', $creneau) : null,
            'departTs' => $depart?->getTimestamp(),
            'finTs' => $depart?->copy()->addMinutes(self::DELAI_MINUTES)->getTimestamp(),
            'maintenantTs' => $c['maintenant']->getTimestamp(),
            'maj' => $c['maintenant']->format('H:i:s'),
            'trajets' => $trajets,
            'routeChoisie' => $c['route'],
            'vues' => $vues,
            'strip' => $strip,
            'manuel' => $c['manuel'],
            'tousLesBillets' => $this->tousLesBillets,
            'filtres' => $this->optionsFiltres(),
            'filtreActif' => filled($this->compagnieId) || filled($this->villeId),
        ];
    }

    // ---------- Actions d'en-tête : embarquement (agent) et exports ----------

    protected function getHeaderActions(): array
    {
        return [
            // Action::make('embarquer')
            //     ->label(fn () => 'Embarquer les voyageurs (' . $this->nbAEmbarquer() . ')')
            //     ->icon('heroicon-o-check-badge')
            //     ->color('success')
            //     ->requiresConfirmation()
            //     ->modalHeading('Confirmer l\'embarquement')
            //     ->modalDescription('Tous les voyageurs prêts de ce départ seront marqués « embarqués ». Ceux qui ont reporté leur voyage n\'en font pas partie.')
            //     ->visible(fn () => $this->peutEmbarquer())
            //     ->action(fn () => $this->embarquerTous()),
            Action::make('embarquer')
                ->label(fn () => 'Embarquer les voyageurs ('.$this->nbAEmbarquer().')')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->modalHeading('Embarquement')
                ->modalDescription('Tout le monde est coché : décochez les absents. Leurs billets restent valables et ils pourront les reporter. 📍 = présent à la gare (position vérifiée), 🙋 = a signalé sa présence.')
                ->modalSubmitActionLabel('Embarquer la sélection')
                ->schema(fn (): array => [
                    CheckboxList::make('presents')
                        ->label('Voyageurs')
                        ->options(fn () => $this->optionsEmbarquement())
                        ->default(fn () => array_keys($this->optionsEmbarquement()))
                        ->bulkToggleable()
                        ->columns(2),
                ])
                ->visible(fn () => $this->peutEmbarquer())
                ->action(fn (array $data) => $this->embarquerSelection($data['presents'] ?? [])),

            ActionGroup::make([
                Action::make('csv')->label('CSV')->icon('heroicon-o-document-text')
                    ->action(fn () => $this->exporterCsv()),
                Action::make('excel')->label('Excel (.xlsx)')->icon('heroicon-o-table-cells')
                    ->action(fn () => $this->exporterExcel()),
                Action::make('pdf')->label('PDF')->icon('heroicon-o-document')
                    ->action(fn () => $this->exporterPdf()),
            ])
                ->label('Exporter la liste')
                ->icon('heroicon-o-arrow-down-tray')
                ->button()
                ->visible(fn () => $this->peutExporter()),
        ];
    }

    /** Billets du départ affiché (créneau et trajet choisis), pour les statuts donnés. */
    protected function billetsDuDepart(array $statuts): Builder
    {
        $c = $this->contexte();

        $requete = Billet::query()
            ->visiblesPar(auth()->user())
            ->whereDate('billets.date_voyage', $c['jour'])
            ->whereRaw('LEFT(billets.heure, 5) = ?', [$c['creneau']])
            ->whereIn('billets.statut', $statuts);

        if ($c['route']) {
            $requete->where('billets.route_transport_id', $c['route']);
        }

        return $requete;
    }

    protected function nbAEmbarquer(): int
    {
        $c = $this->contexte();

        if (! $c['creneau']) {
            return 0;
        }

        return $this->nbAEmbarquerCache ??= $this->billetsDuDepart([BilletStatut::Valide->value])->count();
    }

    /** Seul l'agent de gare embarque, le jour du départ, dans les 90 minutes qui précèdent l'heure. */
    protected function peutEmbarquer(): bool
    {
        if (! auth()->user()->isAgentGare() || $this->tousLesBillets) {
            return false;
        }

        $c = $this->contexte();

        if (! $c['creneau'] || ! $c['jour']->isToday()) {
            return false;
        }

        $ouverture = $c['jour']->copy()->setTimeFromTimeString($c['creneau'])
            ->subMinutes((int) config('billetterie.embarquement_minutes', 90));

        return now()->gte($ouverture) && $this->nbAEmbarquer() > 0;
    }

    /** Un seul clic : tous les billets prêts du départ passent à « utilisé » (embarqué). */
    // public function embarquerTous(): void
    // {
    //     $agent = auth()->user();

    //     abort_unless($agent->isAgentGare() && $this->peutEmbarquer(), 403);

    //     $ok = 0;
    //     $refus = 0;

    //     foreach ($this->billetsDuDepart([BilletStatut::Valide->value])->get() as $billet) {
    //         try {
    //             $billet->changerStatut(BilletStatut::Utilise, $agent, null, 'panel');
    //             $ok++;
    //         } catch (\Illuminate\Auth\Access\AuthorizationException | \DomainException) {
    //             $refus++;
    //         }
    //     }

    //     $this->nbAEmbarquerCache = null;
    //     $this->contexteCache = null;

    //     $notification = Notification::make()
    //         ->title($ok . ' billet(s) embarqué(s)')
    //         ->body($refus > 0 ? $refus . ' billet(s) refusé(s) (expirés ou non valables aujourd\'hui).' : null);

    //     $refus > 0 ? $notification->warning() : $notification->success();
    //     $notification->send();
    // }
    /** @return array<int, string> id du billet => libellé de la liste d'embarquement */
    protected function optionsEmbarquement(): array
    {
        return $this->billetsDuDepart([BilletStatut::Valide->value])
            ->orderBy('billets.reference')
            ->get()
            ->mapWithKeys(function (Billet $billet) {
                $noms = collect((array) $billet->voyageurs)->pluck('nom')->filter()->values();
                $suite = $noms->count() > 1 ? ' (+'.($noms->count() - 1).')' : '';
                $signal = $billet->presence_le ? ($billet->presence_verifiee ? '📍 ' : '🙋 ') : '';

                return [$billet->id => $signal.($noms->first() ?? 'Voyageur').$suite.' — '.$billet->reference];
            })
            ->all();
    }

    /** Les voyageurs cochés embarquent ; les décochés restent valables (absents). */
    public function embarquerSelection(array $ids): void
    {
        $agent = auth()->user();

        abort_unless($agent->isAgentGare() && $this->peutEmbarquer(), 403);

        $ids = array_map('intval', $ids);
        $ok = 0;
        $absents = 0;
        $refus = 0;

        foreach ($this->billetsDuDepart([BilletStatut::Valide->value])->get() as $billet) {
            if (! in_array($billet->id, $ids, true)) {
                $absents++;

                continue;
            }

            try {
                $billet->changerStatut(BilletStatut::Utilise, $agent, null, 'panel');
                $ok++;
            } catch (AuthorizationException|\DomainException) {
                $refus++;
            }
        }

        $this->nbAEmbarquerCache = null;
        $this->contexteCache = null;

        $notification = Notification::make()
            ->title($ok.' billet(s) embarqué(s)')
            ->body(collect([
                $absents > 0 ? $absents.' absent(s) : leurs billets restent valables, ils pourront les reporter.' : null,
                $refus > 0 ? $refus.' billet(s) refusé(s) (expirés ou non valables aujourd\'hui).' : null,
            ])->filter()->implode(' '));

        $refus > 0 ? $notification->warning() : $notification->success();
        $notification->send();
    }

    protected function peutExporter(): bool
    {
        return ! $this->tousLesBillets && (bool) $this->contexte()['creneau'];
    }

    /** @return array{0: array<int,string>, 1: array<int,array<int,mixed>>, 2: string, 3: string} */
    protected function donneesListe(): array
    {
        $c = $this->contexte();

        $billets = $this->billetsDuDepart(BilletStatut::comptabilises())
            ->with(['user', 'route.villeArrivee', 'gare'])
            ->orderBy('billets.route_transport_id')
            ->orderBy('billets.reference')
            ->get();

        $entetes = ['N°', 'Voyageur', 'Code', 'Référence', 'Acheteur', 'Destination', 'Gare', 'Statut'];
        $lignes = [];
        $n = 1;

        foreach ($billets as $billet) {
            foreach ((array) $billet->voyageurs as $voyageur) {
                $lignes[] = [
                    $n++,
                    $voyageur['nom'] ?? '',
                    $voyageur['code'] ?? '',
                    $billet->reference,
                    trim(($billet->user?->prenom ?? '').' '.($billet->user?->nom ?? '')),
                    $billet->route?->villeArrivee?->nom ?? '',
                    $billet->gare?->quartier ?? '',
                    $billet->statut->getLabel(),
                ];
            }
        }

        $heure = str_replace(':', 'h', $c['creneau']);
        $titre = 'Voyageurs de '.$heure.' — '.$c['jour']->copy()->locale('fr')->translatedFormat('l d F Y');
        $fichier = 'voyageurs-'.$c['jour']->toDateString().'-'.$heure;

        return [$entetes, $lignes, $titre, $fichier];
    }

    public function exporterCsv()
    {
        [$entetes, $lignes, , $fichier] = $this->donneesListe();

        return response()->streamDownload(function () use ($entetes, $lignes) {
            $sortie = fopen('php://output', 'w');
            fwrite($sortie, "\xEF\xBB\xBF"); // accents corrects dans Excel
            fputcsv($sortie, $entetes, ';', '"', '');

            foreach ($lignes as $ligne) {
                fputcsv($sortie, $ligne, ';', '"', '');
            }

            fclose($sortie);
        }, $fichier.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function exporterExcel()
    {
        [$entetes, $lignes, , $fichier] = $this->donneesListe();

        $base = tempnam(sys_get_temp_dir(), 'liste');
        $chemin = $base.'.xlsx';
        @unlink($base);

        $ecrivain = new XlsxWriter;
        $ecrivain->openToFile($chemin);
        $ecrivain->addRow(Row::fromValues($entetes));

        foreach ($lignes as $ligne) {
            $ecrivain->addRow(Row::fromValues($ligne));
        }

        $ecrivain->close();

        return response()->download($chemin, $fichier.'.xlsx')->deleteFileAfterSend(true);
    }

    public function exporterPdf()
    {
        [$entetes, $lignes, $titre, $fichier] = $this->donneesListe();

        $contenu = Pdf::loadView('pdf.liste-voyageurs', compact('entetes', 'lignes', 'titre'))
            ->setPaper('a4', 'landscape')
            ->output();

        return response()->streamDownload(fn () => print ($contenu), $fichier.'.pdf', ['Content-Type' => 'application/pdf']);
    }

    // ---------- Calcul du créneau en cours ----------

    protected function vueActive(): string
    {
        return array_key_exists($this->vue, self::VUES) ? $this->vue : 'prets';
    }

    protected function contexte(): array
    {
        if ($this->contexteCache !== null) {
            return $this->contexteCache;
        }

        $maintenant = now();
        $jour = today();
        $voyages = collect();
        $creneau = null;
        $manuel = false;

        // Mode manuel : le créneau épinglé, s'il existe encore.
        if ($this->creneauManuel && preg_match('/^(\d{4}-\d{2}-\d{2})\|(\d{2}:\d{2})$/', $this->creneauManuel, $m)) {
            $jourManuel = Carbon::parse($m[1])->startOfDay();
            $voyagesManuel = $this->voyagesDuJour($jourManuel);

            if ($voyagesManuel->contains('hhmm', $m[2])) {
                [$jour, $voyages, $creneau, $manuel] = [$jourManuel, $voyagesManuel, $m[2], true];
            }
        }

        // Mode automatique : le premier départ dont (heure + 1 minute) n'est pas encore passée.
        // S'il n'y en a plus aujourd'hui, on passe aux jours suivants.
        if (! $creneau) {
            foreach (range(0, 3) as $plus) {
                $jour = today()->addDays($plus);
                $voyages = $this->voyagesDuJour($jour);

                $creneau = $voyages->pluck('hhmm')->unique()->sort()->first(
                    fn (string $hhmm) => $jour->copy()->setTimeFromTimeString($hhmm)->addMinutes(self::DELAI_MINUTES)->greaterThan($maintenant)
                );

                if ($creneau) {
                    break;
                }
            }
        }

        $trajets = $creneau ? $voyages->where('hhmm', $creneau) : collect();
        $route = ($this->routeChoisie && $trajets->contains('route_id', $this->routeChoisie)) ? $this->routeChoisie : null;

        return $this->contexteCache = [
            'jour' => $jour,
            'maintenant' => $maintenant,
            'voyages' => $voyages,
            'creneau' => $creneau,
            'creneaux' => $voyages->pluck('hhmm')->unique()->sort()->values(),
            'route' => $route,
            'manuel' => $manuel,
        ];
    }

    /** Même règle de visibilité que Billet::scopeVisiblesPar(), appliquée aux gares. */
    protected function garesVisibles(): Builder
    {
        $user = auth()->user();

        return match (true) {
            $user->isSuperAdmin() => Gare::query(),
            $user->isAdminCompagnie() => Gare::query()
                ->where('compagnie_id', $user->compagnieId() ?? 0)
                ->when($user->villeId(), fn (Builder $q, $villeId) => $q->where('ville_id', $villeId)),
            $user->isAgentGare() => Gare::query()->whereKey($user->gareId() ?? 0),
            default => Gare::query()->whereRaw('1 = 0'),
        };
    }

    /** Les gares visibles, réduites par les filtres rapides (compagnie, ville). */
    protected function garesFiltrees(): Builder
    {
        return $this->garesVisibles()
            ->when(filled($this->compagnieId), fn (Builder $q) => $q->where('compagnie_id', (int) $this->compagnieId))
            ->when(filled($this->villeId), fn (Builder $q) => $q->where('ville_id', (int) $this->villeId));
    }

    /** Choix proposés dans les sélecteurs : uniquement ce que l'utilisateur a le droit de voir. */
    protected function optionsFiltres(): array
    {
        $gares = $this->garesVisibles()->with('compagnie')->get();

        $compagnies = $gares->pluck('compagnie.nom', 'compagnie_id')->filter()->sort();

        $pourVilles = filled($this->compagnieId)
            ? $gares->where('compagnie_id', (int) $this->compagnieId)
            : $gares;

        $villes = Ville::query()
            ->whereIn('id', $pourVilles->pluck('ville_id')->unique()->values())
            ->orderBy('nom')
            ->pluck('nom', 'id');

        return compact('compagnies', 'villes');
    }

    /**
     * Tous les départs (route + heure) d'un jour pour le périmètre de l'utilisateur,
     * avec le nombre de voyageurs par vue (prêts, embarqués, en attente, annulés, reportés).
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function voyagesDuJour(Carbon $jour): Collection
    {
        $gares = $this->garesFiltrees()->with('compagnie')->get()->keyBy('id');

        if ($gares->isEmpty()) {
            return collect();
        }

        $routes = RouteTransport::query()->whereIn('gare_depart_id', $gares->keys())->get()->keyBy('id');

        $villes = Ville::query()
            ->whereIn('id', $routes->pluck('ville_arrivee_id')->merge($gares->pluck('ville_id'))->unique()->values())
            ->pluck('nom', 'id');

        $code = self::JOURS[$jour->dayOfWeek];

        // Départs programmés ce jour-là (routes actives uniquement).
        $programmes = Horaire::query()
            ->whereIn('route_transport_id', $routes->keys())
            ->get()
            ->filter(fn (Horaire $h) => $routes[$h->route_transport_id]->actif
                && (blank($h->jours_semaine) || in_array($code, $h->jours_semaine, true)))
            ->map(fn (Horaire $h) => $h->route_transport_id.'|'.substr((string) $h->heure, 0, 5))
            ->values();

        // Voyageurs par route, heure et statut.
        $lignes = Billet::query()
            ->visiblesPar(auth()->user())
            ->whereIn('billets.gare_id', $gares->keys())
            ->whereDate('billets.date_voyage', $jour)
            ->selectRaw('billets.route_transport_id as route_id, LEFT(billets.heure, 5) as hhmm, billets.statut as statut, SUM(billets.travelers_count) as voyageurs')
            ->groupByRaw('billets.route_transport_id, LEFT(billets.heure, 5), billets.statut')
            ->get();

        $compteurs = [];

        foreach ($lignes as $ligne) {
            $vue = collect(self::VUES)->search(fn (array $v) => in_array($ligne->statut->value, $v['statuts'], true));

            if ($vue === false) {
                continue;
            }

            $cle = $ligne->route_id.'|'.$ligne->hhmm;
            $compteurs[$cle][$vue] = ($compteurs[$cle][$vue] ?? 0) + (int) $ligne->voyageurs;
        }

        // Voyageurs qui ont reporté leur voyage depuis ce départ (billets déplacés vers un autre jour).
        $reports = ReportBillet::query()
            ->join('billets', 'billets.id', '=', 'reports_billets.billet_id')
            ->whereDate('reports_billets.ancienne_date', $jour)
            ->whereIn('reports_billets.route_transport_id', $routes->keys())
            ->selectRaw('reports_billets.route_transport_id as route_id, reports_billets.ancienne_heure as hhmm, SUM(billets.travelers_count) as voyageurs')
            ->groupBy('reports_billets.route_transport_id', 'reports_billets.ancienne_heure')
            ->get();

        foreach ($reports as $report) {
            $compteurs[$report->route_id.'|'.$report->hhmm]['reportes'] = (int) $report->voyageurs;
        }

        // Heures programmées + heures ayant des billets (un billet n'est jamais caché).
        return $programmes
            ->merge(array_keys($compteurs))
            ->unique()
            ->values()
            ->map(function (string $cle) use ($routes, $gares, $villes, $compteurs) {
                [$routeId, $hhmm] = explode('|', $cle, 2);
                $route = $routes[(int) $routeId] ?? null;

                if (! $route) {
                    return null;
                }

                $gare = $gares[$route->gare_depart_id];

                return [
                    'route_id' => (int) $routeId,
                    'hhmm' => $hhmm,
                    'compagnie' => $gare->compagnie?->nom ?? '—',
                    'gare' => $gare->quartier,
                    'depart_ville' => $villes[$gare->ville_id] ?? '—',
                    'arrivee_ville' => $villes[$route->ville_arrivee_id] ?? '—',
                    'prets' => $compteurs[$cle]['prets'] ?? 0,
                    'embarques' => $compteurs[$cle]['embarques'] ?? 0,
                    'attente' => $compteurs[$cle]['attente'] ?? 0,
                    'annules' => $compteurs[$cle]['annules'] ?? 0,
                    'reportes' => $compteurs[$cle]['reportes'] ?? 0,
                ];
            })
            ->filter()
            ->sortBy([['hhmm', 'asc'], ['gare', 'asc'], ['arrivee_ville', 'asc']])
            ->values();
    }
}
