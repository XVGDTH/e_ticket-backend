<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\PlacesInsuffisantes;
use App\Http\Controllers\Controller;
use App\Models\Billet;
use App\Models\RouteTransport;
use App\Services\Billetterie;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BilletController extends Controller
{
    public function store(Request $request)
    {
        $max = (int) config('billetterie.voyageurs_max', 10);
        $jours = (int) config('billetterie.jours_reservation_max', 90);

        $data = $request->validate([
            'route_transport_id' => ['required', 'integer'],
            'gare_id' => ['nullable', 'integer'], // facultatif : la gare est toujours celle de la route
            'date_voyage' => ['required', 'date', 'after_or_equal:today', 'before_or_equal:' . now()->addDays($jours)->toDateString()],
            'heure' => ['required', 'string', 'max:10'],
            'travelers_count' => ['required', 'integer', 'min:1', 'max:' . $max],
            'montant_transport' => ['nullable', 'integer'], // contrôlé : le prix est recalculé par le serveur
            'taxe' => ['nullable', 'integer', 'min:0'],
            'operateur_paiement' => ['required', 'string', 'max:50'],
            'voyageurs' => ['required', 'array', 'min:1', 'max:' . $max],
            'voyageurs.*' => ['required', 'string', 'min:2', 'max:100'],
        ]);

        // 1. La route doit exister, être active, et sa compagnie aussi.
        $route = RouteTransport::query()
            ->where('actif', true)
            ->whereHas('compagnie', fn ($q) => $q->where('actif', true))
            ->with(['horaires'])
            ->find($data['route_transport_id']);

        if (! $route) {
            return $this->refus("Cette route n'est pas disponible.");
        }

        if (isset($data['gare_id']) && (int) $data['gare_id'] !== (int) $route->gare_depart_id) {
            return $this->refus('La gare ne correspond pas à la route choisie.');
        }

        if ((int) $data['travelers_count'] !== count($data['voyageurs'])) {
            return $this->refus('Le nombre de voyageurs ne correspond pas à la liste des noms.');
        }

        // 2. Le départ doit exister ce jour-là et ne pas être passé.
        $date = Carbon::parse($data['date_voyage'])->startOfDay();
        $heure = Billetterie::normaliserHeure($data['heure']);

        if (! in_array($heure, Billetterie::horairesDuJour($route, $date->toDateString()), true)) {
            return $this->refus("Aucun départ à cette heure à cette date.");
        }

        if ($date->isToday() && now()->gte($date->copy()->setTimeFromTimeString($heure))) {
            return $this->refus('Ce départ est déjà passé.');
        }

        // 3. Le prix vient du serveur, jamais de l'application.
        $montant = $route->prix * $data['travelers_count'];

        if (isset($data['montant_transport']) && (int) $data['montant_transport'] !== $montant) {
            return $this->refus('Le prix a changé.', 422, ['montant_attendu' => $montant]);
        }

        $taxeParVoyageur = config('billetterie.taxe_par_voyageur');

        if ($taxeParVoyageur !== null) {
            $taxe = $taxeParVoyageur * $data['travelers_count'];

            if (isset($data['taxe']) && (int) $data['taxe'] !== $taxe) {
                return $this->refus('La taxe a changé.', 422, ['taxe_attendue' => $taxe]);
            }
        } else {
            // Règle de taxe non définie : on accepte la valeur envoyée, bornée à 20 % du montant.
            $taxe = (int) ($data['taxe'] ?? 0);

            if ($taxe > intdiv($montant, 5)) {
                return $this->refus('Taxe invalide.');
            }
        }

        // 4. Capacité : les ventes d'une même route sont traitées une par une.
        try {
            $billet = DB::transaction(function () use ($request, $route, $data, $date, $heure, $montant, $taxe) {
                RouteTransport::whereKey($route->id)->lockForUpdate()->first();

                $restantes = Billetterie::placesParDepart() - (int) Billetterie::vendus($route->id, $date->toDateString())->get($heure, 0);

                if ($data['travelers_count'] > $restantes) {
                    throw new PlacesInsuffisantes(max(0, $restantes));
                }

                do {
                    $reference = strtoupper(Str::random(4)) . '-' . random_int(100000, 999999);
                } while (Billet::where('reference', $reference)->exists());

                // Codes passagers : signature avec la clé secrète de l'application (non devinable).
                $voyageurs = collect($data['voyageurs'])->values()->map(fn ($nom, $i) => [
                    'nom' => trim($nom),
                    'code' => strtoupper(substr(hash_hmac('sha256', "$reference|$i|" . trim($nom), (string) config('app.key')), 0, 8)),
                ])->all();

                // ATTENTION : le billet est créé comme payé. À brancher sur la confirmation réelle
                // du paiement (Orange Money, Moov Money...) avant la mise en production.
                return Billet::create([
                    'reference' => $reference,
                    'user_id' => $request->user()->id,
                    'route_transport_id' => $route->id,
                    'gare_id' => $route->gare_depart_id,
                    'date_voyage' => $date->toDateString(),
                    'heure' => trim($data['heure']),
                    'travelers_count' => $data['travelers_count'],
                    'montant_transport' => $montant,
                    'taxe' => $taxe,
                    'operateur_paiement' => $data['operateur_paiement'],
                    'voyageurs' => $voyageurs,
                    'date_paiement' => now(),
                ]);
            });
        } catch (PlacesInsuffisantes $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'places_restantes' => $e->restantes,
            ], 409);
        }

        return response()->json(['success' => true, 'billet' => $billet->fresh()]);
    }

    public function mesBillets(Request $request)
    {
        return $request->user()->billets()->with(['route.compagnie', 'route.villeArrivee', 'gare'])->latest()->get();
    }

    private function refus(string $message, int $statut = 422, array $extra = [])
    {
        return response()->json(array_merge(['success' => false, 'message' => $message], $extra), $statut);
    }
}