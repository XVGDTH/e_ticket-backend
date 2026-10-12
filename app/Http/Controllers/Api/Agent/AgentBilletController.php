<?php

namespace App\Http\Controllers\Api\Agent;

use App\Enums\BilletStatut;
use App\Http\Controllers\Controller;
use App\Models\Billet;
use App\Models\RouteTransport;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AgentBilletController extends Controller
{
    private const JOURS_ISO = ['lun' => 1, 'mar' => 2, 'mer' => 3, 'jeu' => 4, 'ven' => 5, 'sam' => 6, 'dim' => 7];

    /** Billets de MA gare pour un jour (aujourd'hui par défaut). */
    public function index(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'statut' => ['nullable', Rule::enum(BilletStatut::class)],
            'heure' => ['nullable', 'string', 'max:10'],
            'q' => ['nullable', 'string', 'max:50'],
            'par_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $date = $donnees['date'] ?? today()->toDateString();

        $billets = Billet::visiblesPar($request->user())
            ->with(['user:id,prenom,nom,telephone', 'route.villeArrivee:id,nom'])
            ->whereDate('date_voyage', $date)
            ->when($donnees['statut'] ?? null, fn ($q, $statut) => $q->where('statut', $statut))
            ->when($donnees['heure'] ?? null, fn ($q, $heure) => $q->where('heure', 'like', $heure . '%'))
            ->when($donnees['q'] ?? null, fn ($q, $texte) => $q->where('reference', 'like', '%' . addcslashes($texte, '%_') . '%'))
            ->orderBy('heure')
            ->orderBy('reference')
            ->paginate($donnees['par_page'] ?? 50);

        return response()->json([
            'date' => $date,
            'billets' => $billets->getCollection()->map(fn (Billet $b) => $this->formater($b))->values(),
            'pagination' => [
                'page' => $billets->currentPage(),
                'par_page' => $billets->perPage(),
                'total' => $billets->total(),
                'pages' => $billets->lastPage(),
            ],
        ]);
    }

    /** Un billet de MA gare. Un billet d'une autre gare répond 404 : on ne révèle pas son existence. */
    public function show(Request $request, string $reference): JsonResponse
    {
        $billet = $this->billetVisible($request, $reference);

        if (! $billet) {
            return $this->introuvable();
        }

        return response()->json(['billet' => $this->formater($billet, true)]);
    }

    /** Embarquement : valide le billet (une seule fois, le jour du voyage). */
    public function valider(Request $request, string $reference): JsonResponse
    {
        $billet = $this->billetVisible($request, $reference);

        if (! $billet) {
            return $this->introuvable();
        }

        try {
            $billet = $billet->changerStatut(BilletStatut::Utilise, $request->user(), null, 'api_agent');
        } catch (AuthorizationException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (\DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'billet' => $this->formater($this->billetVisible($request, $reference), true),
            ], 422);
        }

        return response()->json([
            'message' => 'Billet validé. Bon voyage !',
            'billet' => $this->formater($billet->load(['user:id,prenom,nom,telephone', 'route.villeArrivee:id,nom', 'evenements.user:id,prenom,nom']), true),
        ]);
    }

    /** Les départs de MA gare pour un jour : ventes, embarqués, remplissage. */
    public function departs(Request $request): JsonResponse
    {
        $donnees = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);

        $user = $request->user();
        $date = $donnees['date'] ?? today()->toDateString();
        $iso = Carbon::parse($date)->dayOfWeekIso;
        $places = (int) config('analytics.places_par_depart', 60);

        $ventes = Billet::visiblesPar($user)
            ->whereDate('date_voyage', $date)
            ->whereIn('statut', BilletStatut::comptabilises())
            ->selectRaw("route_transport_id, heure, COUNT(*) as billets, SUM(travelers_count) as voyageurs, SUM(CASE WHEN statut = 'utilise' THEN travelers_count ELSE 0 END) as embarques")
            ->groupBy('route_transport_id', 'heure')
            ->get()
            ->groupBy(fn ($l) => $l->route_transport_id . '|' . $this->normaliserHeure((string) $l->heure))
            ->map(fn ($groupe) => [
                'billets' => (int) $groupe->sum('billets'),
                'voyageurs' => (int) $groupe->sum('voyageurs'),
                'embarques' => (int) $groupe->sum('embarques'),
            ]);

        $departs = [];

        $routes = RouteTransport::where('gare_depart_id', $user->gareId())
            ->where('actif', true)
            ->with(['villeArrivee:id,nom', 'horaires'])
            ->get();

        foreach ($routes as $route) {
            foreach ($route->horaires as $horaire) {
                if (! $this->jourAutorise($horaire->jours_semaine, $iso)) {
                    continue;
                }

                $heure = $this->normaliserHeure((string) $horaire->heure);
                $vente = $ventes->get($route->id . '|' . $heure, ['billets' => 0, 'voyageurs' => 0, 'embarques' => 0]);

                $departs[] = [
                    'route_id' => $route->id,
                    'destination' => $route->villeArrivee?->nom,
                    'heure' => $heure,
                    'prix' => $route->prix,
                    'billets' => $vente['billets'],
                    'voyageurs' => $vente['voyageurs'],
                    'embarques' => $vente['embarques'],
                    'places' => $places,
                    'taux_remplissage' => round($vente['voyageurs'] / $places * 100, 1),
                ];
            }
        }

        usort($departs, fn ($a, $b) => [$a['heure'], $a['destination']] <=> [$b['heure'], $b['destination']]);

        $totaux = Billet::visiblesPar($user)
            ->whereDate('date_voyage', $date)
            ->whereIn('statut', BilletStatut::comptabilises())
            ->selectRaw("COUNT(*) as billets, COALESCE(SUM(travelers_count), 0) as voyageurs, COALESCE(SUM(montant_transport), 0) as ca, COALESCE(SUM(CASE WHEN statut = 'utilise' THEN travelers_count ELSE 0 END), 0) as embarques")
            ->first();

        return response()->json([
            'date' => $date,
            'resume' => [
                'billets' => (int) $totaux->billets,
                'voyageurs' => (int) $totaux->voyageurs,
                'embarques' => (int) $totaux->embarques,
                'restant_a_embarquer' => (int) $totaux->voyageurs - (int) $totaux->embarques,
                'chiffre_affaires' => (int) $totaux->ca,
            ],
            'departs' => $departs,
        ]);
    }

    // ---------- Outils ----------

        /** Contrôle d'un passager : référence + nom + code, dans MA gare uniquement. */
    public function verifier(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'reference' => ['required', 'string', 'max:40'],
            'nom' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:20'],
        ]);

        $billet = $this->billetVisible($request, strtoupper(trim($donnees['reference'])));

        $refus = fn (string $raison, ?Billet $b = null) => response()->json([
            'valide' => false,
            'raison' => $raison,
            'billet' => $b ? $this->formater($b) : null,
        ]);

        if (! $billet) {
            return $refus('Billet introuvable pour votre gare.');
        }

        $voyageur = collect($billet->voyageurs)->first(
            fn ($v) => mb_strtolower(trim((string) ($v['nom'] ?? ''))) === mb_strtolower(trim($donnees['nom']))
        );

        if (! $voyageur || ! hash_equals(strtoupper((string) ($voyageur['code'] ?? '')), strtoupper(trim($donnees['code'])))) {
            return $refus('Nom ou code incorrect.');
        }

        if ($billet->statut !== BilletStatut::Valide) {
            return $refus("Billet déjà « {$billet->statut->getLabel()} ».", $billet);
        }

        if (! $billet->date_voyage->isToday()) {
            return $refus('Billet valable le ' . $billet->date_voyage->format('d/m/Y') . '.', $billet);
        }

        return response()->json(['valide' => true, 'raison' => null, 'billet' => $this->formater($billet, true)]);
    }

    private function billetVisible(Request $request, string $reference): ?Billet
    {
        return Billet::visiblesPar($request->user())
            ->with(['user:id,prenom,nom,telephone', 'route.villeArrivee:id,nom', 'evenements.user:id,prenom,nom'])
            ->where('reference', $reference)
            ->first();
    }

    private function introuvable(): JsonResponse
    {
        return response()->json(['message' => 'Billet introuvable.'], 404);
    }

    private function formater(Billet $b, bool $detail = false): array
    {
        $client = $b->user;

        $donnees = [
            'reference' => $b->reference,
            'statut' => ['valeur' => $b->statut->value, 'libelle' => $b->statut->getLabel()],
            'date_voyage' => $b->date_voyage->toDateString(),
            'heure' => $b->heure,
            'destination' => $b->route?->villeArrivee?->nom,
            'nombre_voyageurs' => $b->travelers_count,
            'montant_transport' => $b->montant_transport,
            'taxe' => $b->taxe,
            'operateur_paiement' => $b->operateur_paiement,
            'client' => $client ? [
                'nom' => trim("{$client->prenom} {$client->nom}"),
                'telephone' => $this->masquer((string) $client->telephone),
            ] : null,
        ];

        if ($detail) {
            $donnees['voyageurs'] = $b->voyageurs;
            $donnees['date_paiement'] = $b->date_paiement?->toIso8601String();
            $donnees['historique'] = $b->evenements->sortBy('id')->map(fn ($e) => [
                'statut' => $e->statut_apres,
                'par' => $e->user ? trim("{$e->user->prenom} {$e->user->nom}") : null,
                'motif' => $e->motif,
                'source' => $e->source,
                'le' => $e->created_at?->toIso8601String(),
            ])->values();
        }

        return $donnees;
    }

    /** Le numéro du client n'est jamais exposé en entier. */
    private function masquer(string $telephone): string
    {
        return strlen($telephone) > 4 ? substr($telephone, 0, 2) . '****' . substr($telephone, -2) : '****';
    }

    /** "08h30", "8:30" et "08:30:00" deviennent tous "08:30". */
    private function normaliserHeure(string $heure): string
    {
        if (preg_match('/^(\d{1,2})\s*[h:]\s*(\d{2})/i', trim($heure), $m)) {
            return sprintf('%02d:%s', (int) $m[1], $m[2]);
        }

        return trim($heure);
    }

    private function jourAutorise(?array $jours, int $iso): bool
    {
        if (! $jours) {
            return true;
        }

        foreach ($jours as $jour) {
            if ((self::JOURS_ISO[mb_substr(Str::lower(Str::ascii((string) $jour)), 0, 3)] ?? null) === $iso) {
                return true;
            }
        }

        return false;
    }
}