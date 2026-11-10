<?php

namespace App\Filament\Resources\Billets\Pages;

use App\Enums\BilletStatut;
use App\Filament\Resources\Billets\BilletResource;
use App\Models\Billet;
use App\Models\Gare;
use App\Models\Horaire;
use App\Models\RouteTransport;
use App\Models\Ville;
use Filament\Actions\Action;
use Filament\Resources\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DepartsDuJour extends Page
{
    protected static string $resource = BilletResource::class;

    protected string $view = 'filament.resources.billets.pages.departs-du-jour';

    /** Même format que horaires.jours_semaine (Carbon::dayOfWeek : 0 = dimanche). */
    private const JOURS = ['dim', 'lun', 'mar', 'mer', 'jeu', 'ven', 'sam'];

    private const CARTES = [
        'en_attente' => ['label' => 'En attente de paiement', 'couleur' => '#f59e0b'],
        'reserve' => ['label' => 'Réservés (jours suivants)', 'couleur' => '#3b82f6'],
        'annule' => ['label' => 'Annulés', 'couleur' => '#ef4444'],
        'rembourse' => ['label' => 'Remboursés', 'couleur' => '#8b5cf6'],
    ];

    /** Jour affiché (Y-m-d). */
    public string $date = '';

    public function getTitle(): string
    {
        return 'Départs du jour';
    }

    public function mount(): void
    {
        $this->date = today()->toDateString();
    }

    public function jourPrecedent(): void
    {
        $this->date = Carbon::parse($this->date)->subDay()->toDateString();
    }

    public function jourSuivant(): void
    {
        $this->date = Carbon::parse($this->date)->addDay()->toDateString();
    }

    public function aujourdhui(): void
    {
        $this->date = today()->toDateString();
    }

    protected function getViewData(): array
    {
        $jour = Carbon::parse($this->date)->startOfDay();

        $cartes = [];

        foreach (self::CARTES as $cle => $definition) {
            $cartes[$cle] = $definition + ['total' => $this->requeteStatut($cle, $jour)->count()];
        }

        return [
            'jour' => $jour,
            'trajets' => $this->trajets($jour),
            'cartesStatut' => $cartes,
        ];
    }

    // ---------- Périmètre de l'utilisateur (même règle que Billet::scopeVisiblesPar) ----------

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

    // ---------- Cartes des trajets du jour ----------

    /** @return Collection<string, Collection<string, Collection>>  compagnie => « Ville → Ville » => gares */
    protected function trajets(Carbon $jour): Collection
    {
        $gares = $this->garesVisibles()->with('compagnie')->get()->keyBy('id');

        if ($gares->isEmpty()) {
            return collect();
        }

        $routes = RouteTransport::query()->whereIn('gare_depart_id', $gares->keys())->get()->keyBy('id');

        $villes = Ville::query()
            ->whereIn('id', $routes->pluck('ville_arrivee_id')->merge($gares->pluck('ville_id'))->unique()->values())
            ->pluck('nom', 'id');

        $code = self::JOURS[$jour->dayOfWeek];

        // Heures programmées ce jour-là, pour les routes actives.
        $programmes = Horaire::query()
            ->whereIn('route_transport_id', $routes->keys())
            ->get()
            ->filter(fn (Horaire $h) => $routes[$h->route_transport_id]->actif
                && (blank($h->jours_semaine) || in_array($code, $h->jours_semaine, true)))
            ->groupBy('route_transport_id')
            ->map(fn (Collection $liste) => $liste->map(fn (Horaire $h) => substr((string) $h->heure, 0, 5)));

        // Billets du jour (prêts + embarqués), par route, heure et statut.
        $lignes = Billet::query()
            ->visiblesPar(auth()->user())
            ->whereDate('billets.date_voyage', $jour)
            ->whereIn('billets.statut', BilletStatut::comptabilises())
            ->selectRaw('billets.route_transport_id as route_id, LEFT(billets.heure, 5) as hhmm, billets.statut as statut, COUNT(*) as billets, SUM(billets.travelers_count) as voyageurs')
            ->groupByRaw('billets.route_transport_id, LEFT(billets.heure, 5), billets.statut')
            ->get()
            ->groupBy('route_id');

        $maintenant = now();
        $entrees = collect();

        foreach ($routes as $route) {
            $gare = $gares[$route->gare_depart_id];
            $stats = $lignes->get($route->id, collect())->groupBy('hhmm');

            // Heures programmées + heures ayant des billets (pour ne jamais cacher un billet).
            $heures = collect($programmes->get($route->id, collect())->all())
                ->merge($stats->keys())
                ->unique()
                ->sort()
                ->values();

            if ($heures->isEmpty()) {
                continue;
            }

            $chips = $heures->map(function (string $hhmm) use ($stats, $jour, $maintenant, $route) {
                $ligne = $stats->get($hhmm, collect());
                $prets = (int) ($ligne->firstWhere('statut', BilletStatut::Valide)?->voyageurs ?? 0);
                $embarques = (int) ($ligne->firstWhere('statut', BilletStatut::Utilise)?->voyageurs ?? 0);
                $passe = $jour->copy()->setTimeFromTimeString($hhmm)->lessThan($maintenant);

                return [
                    'cle' => $route->id.'|'.$hhmm,
                    'label' => str_replace(':', 'h', $hhmm),
                    'prets' => $prets,
                    'embarques' => $embarques,
                    'passe' => $passe,
                    // Départ passé mais des billets valides n'ont pas été embarqués.
                    'alerte' => $passe && $prets > 0,
                ];
            });

            // Prochain départ de cette gare (uniquement quand on regarde aujourd'hui).
            $prochain = $jour->isToday() ? $chips->search(fn (array $c) => ! $c['passe']) : false;

            $chips = $chips
                ->map(fn (array $c, int $i) => $c + ['prochain' => $prochain !== false && $i === $prochain])
                ->all();

            $entrees->push([
                'compagnie' => $gare->compagnie?->nom ?? '—',
                'depart_ville' => $villes[$gare->ville_id] ?? '—',
                'arrivee_ville' => $villes[$route->ville_arrivee_id] ?? '—',
                'gare' => $gare->quartier,
                'telephone' => $gare->telephone,
                'prix' => $route->prix,
                'duree' => $route->duree_max,
                'chips' => $chips,
            ]);
        }

        return $entrees
            ->groupBy('compagnie')
            ->map(fn (Collection $parCompagnie) => $parCompagnie
                ->groupBy(fn (array $e) => $e['depart_ville'].' → '.$e['arrivee_ville'])
                ->map(fn (Collection $g) => $g->sortBy('gare')->values())
                ->sortKeys())
            ->sortKeys();
    }

    // ---------- Cartes de statut (en haut de page) ----------

    protected function requeteStatut(string $cle, Carbon $jour): Builder
    {
        $requete = Billet::query()->visiblesPar(auth()->user());

        return match ($cle) {
            'en_attente' => $requete->where('billets.statut', BilletStatut::EnAttentePaiement->value)->whereDate('billets.date_voyage', $jour),
            'annule' => $requete->where('billets.statut', BilletStatut::Annule->value)->whereDate('billets.date_voyage', $jour),
            'rembourse' => $requete->where('billets.statut', BilletStatut::Rembourse->value)->whereDate('billets.date_voyage', $jour),
            'reserve' => $requete->where('billets.statut', BilletStatut::Valide->value)->whereDate('billets.date_voyage', '>', $jour),
            default => $requete->whereRaw('1 = 0'),
        };
    }

    // ---------- Panneau latéral : liste des billets (un clic sur une heure ou une carte de statut) ----------

    public function voirBilletsAction(): Action
    {
        return Action::make('voirBillets')
            ->modalHeading(fn (array $arguments): string => $this->titreListe($arguments['cle'] ?? ''))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Fermer')
            ->slideOver()
            ->modalContent(fn (array $arguments) => view(
                'filament.resources.billets.pages.liste-billets',
                $this->donneesListe($arguments['cle'] ?? '')
            ));
    }

    /** $cle = « voyage:{route}|{HH:MM} » ou « statut:{en_attente|reserve|annule|rembourse} ». */
    protected function donneesListe(string $cle): array
    {
        $jour = Carbon::parse($this->date)->startOfDay();
        [$type, $valeur] = array_pad(explode(':', $cle, 2), 2, '');

        if ($type === 'voyage') {
            [$routeId, $hhmm] = array_pad(explode('|', $valeur, 2), 2, '');

            $requete = Billet::query()
                ->visiblesPar(auth()->user())
                ->whereDate('billets.date_voyage', $jour)
                ->where('billets.route_transport_id', (int) $routeId)
                ->whereRaw('LEFT(billets.heure, 5) = ?', [$hhmm])
                ->whereIn('billets.statut', BilletStatut::comptabilises())
                ->orderByRaw("billets.statut = 'valide' desc");

            $couleur = '#22c55e';
        } else {
            $requete = $this->requeteStatut($valeur, $jour);
            $couleur = self::CARTES[$valeur]['couleur'] ?? '#6b7280';
        }

        $total = (clone $requete)->count();

        $billets = $requete
            ->with(['user', 'gare', 'route'])
            ->orderBy('billets.heure')
            ->orderBy('billets.id')
            ->limit(200)
            ->get();

        $villes = Ville::query()
            ->whereIn('id', $billets->pluck('route.ville_arrivee_id')->filter()->unique()->values())
            ->pluck('nom', 'id');

        return compact('billets', 'total', 'villes', 'couleur');
    }

    protected function titreListe(string $cle): string
    {
        $jour = Carbon::parse($this->date)->locale('fr');
        [$type, $valeur] = array_pad(explode(':', $cle, 2), 2, '');

        if ($type === 'statut') {
            $carte = self::CARTES[$valeur] ?? null;

            if (! $carte) {
                return 'Billets';
            }

            return $carte['label'].($valeur === 'reserve' ? ' — après le ' : ' — ').$jour->translatedFormat('l d F');
        }

        [$routeId, $hhmm] = array_pad(explode('|', $valeur, 2), 2, '');

        $route = RouteTransport::query()
            ->whereIn('gare_depart_id', $this->garesVisibles()->pluck('id'))
            ->find((int) $routeId);

        if (! $route) {
            return 'Billets du voyage';
        }

        $gare = Gare::find($route->gare_depart_id);
        $arrivee = Ville::find($route->ville_arrivee_id)?->nom;

        return 'Départ de '.str_replace(':', 'h', $hhmm).' · '.$gare?->quartier.' → '.$arrivee;
    }
}
