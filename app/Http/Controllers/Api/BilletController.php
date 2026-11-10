<?php

namespace App\Http\Controllers\Api;

use App\Enums\BilletStatut;
use App\Exceptions\PlacesInsuffisantes;
use App\Http\Controllers\Controller;
use App\Models\AvisGare;
use App\Models\Billet;
use App\Models\NotificationClient;
use App\Models\RouteTransport;
use App\Models\Ville;
use App\Services\Billetterie;
use App\Services\BilletToken;
use App\Services\Frais;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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
            'date_voyage' => ['required', 'date', 'after_or_equal:today', 'before_or_equal:'.now()->addDays($jours)->toDateString()],
            'heure' => ['required', 'string', 'max:10'],
            'travelers_count' => ['required', 'integer', 'min:1', 'max:'.$max],
            'montant_transport' => ['nullable', 'integer'], // contrôlé : le prix est recalculé par le serveur
            'taxe' => ['nullable', 'integer', 'min:0'],
            'operateur_paiement' => ['required', 'string', 'max:50'],
            'voyageurs' => ['required', 'array', 'min:1', 'max:'.$max],
            'voyageurs.*' => ['required', 'string', 'min:2', 'max:100'],
            'consentement' => ['accepted'],

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
            return $this->refus('Aucun départ à cette heure à cette date.');
        }

        if ($date->isToday() && now()->gte($date->copy()->setTimeFromTimeString($heure))) {
            return $this->refus('Ce départ est déjà passé.');
        }

        // 3. Le prix vient du serveur, jamais de l'application.
        $montant = $route->prix * $data['travelers_count'];

        if (isset($data['montant_transport']) && (int) $data['montant_transport'] !== $montant) {
            return $this->refus('Le prix a changé.', 422, ['montant_attendu' => $montant]);
        }

        // Taxe de service : forfait unique par achat, quel que soit le nombre de voyageurs.
        // $taxe = (int) config('billetterie.Frais::calculer($route, $prixUnitaire, $nbVoyageurs)['montant']', 300);

        // if (isset($data['taxe']) && (int) $data['taxe'] !== $taxe) {
        //     return $this->refus('La taxe a changé.', 422, ['taxe_attendue' => $taxe]);
        // }
        // // Règle de taxe non définie : on accepte la valeur envoyée, bornée à 20 % du montant.
        // $taxe = (int) ($data['taxe'] ?? 0);

        // if ($taxe > intdiv($montant, 5)) {
        //     return $this->refus('Taxe invalide.');
        // }
        // Frais de service : un seul forfait par achat, calculé par le serveur selon le prix du billet,
        // le trajet, la compagnie et la gare. L'application n'envoie que le montant qu'elle a affiché.
        $taxe = Frais::calculer($route, (int) $route->prix, (int) $data['travelers_count'])['montant'];

        if (isset($data['taxe']) && (int) $data['taxe'] !== $taxe) {
            return $this->refus('Les frais ont changé.', 422, ['taxe_attendue' => $taxe]);
        }

        // 4. Capacité : les ventes d'une même route sont traitées une par une.
        try {
            $billet = DB::transaction(function () use ($request, $route, $data, $date, $heure, $montant, $taxe) {
                RouteTransport::whereKey($route->id)->lockForUpdate()->first();

                // $restantes = Billetterie::placesParDepart() - (int) Billetterie::vendus($route->id, $date->toDateString())->get($heure, 0);
                $restantes = Billetterie::placesApp($route) - (int) Billetterie::vendus($route->id, $date->toDateString())->get($heure, 0);

                if ($data['travelers_count'] > $restantes) {
                    throw new PlacesInsuffisantes(max(0, $restantes));
                }

                do {
                    $reference = strtoupper(Str::random(4)).'-'.random_int(100000, 999999);
                } while (Billet::where('reference', $reference)->exists());

                // Codes passagers : signature avec la clé secrète de l'application (non devinable).
                $voyageurs = collect($data['voyageurs'])->values()->map(fn ($nom, $i) => [
                    'nom' => trim($nom),
                    'code' => strtoupper(substr(hash_hmac('sha256', "$reference|$i|".trim($nom), (string) config('app.key')), 0, 8)),
                ])->all();

                // ATTENTION : le billet est créé comme payé. À brancher sur la confirmation réelle
                // du paiement (Orange Money, Moov Money...) avant la mise en production.
                return Billet::create([
                    'reference' => $reference,
                    'user_id' => $request->user()->id,
                    'route_transport_id' => $route->id,
                    'gare_id' => $route->gare_depart_id,
                    'date_voyage' => $date->toDateString(),
                    // 'heure' => trim($data['heure']),
                    'heure' => $heure,
                    'travelers_count' => $data['travelers_count'],
                    'montant_transport' => $montant,
                    'taxe' => $taxe,
                    'operateur_paiement' => $data['operateur_paiement'],
                    'voyageurs' => $voyageurs,
                    'date_paiement' => now(),
                    'validite_jours' => (int) ($route->compagnie?->validite_jours ?: config('billetterie.validite_jours', 12)),
                    'consentement_le' => now(),
                    'consentement_version' => (string) config('billetterie.cgu_version', '1.0'),
                ]);
            });
        } catch (PlacesInsuffisantes $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'places_restantes' => $e->restantes,
            ], 409);
        }

        // return response()->json(['success' => true, 'billet' => $billet->fresh()]);
        $billet = $billet->fresh(['route.compagnie', 'route.villeArrivee', 'gare']);

        return response()->json(['success' => true, 'billet' => $this->formater($billet)]);
    }

    public function mesBillets(Request $request)
    {
        $billets = $request->user()->billets()
            ->with(['route.compagnie', 'route.villeArrivee', 'gare'])
            ->latest()
            ->get();

        $villes = Ville::whereIn('id', $billets->pluck('gare.ville_id')->filter()->unique()->values())
            ->pluck('nom', 'id');

        $notes = AvisGare::whereIn('billet_id', $billets->pluck('id'))->pluck('billet_id');

        return response()->json(
            $billets->map(fn (Billet $b) => $this->formater($b, $villes, $notes->contains($b->id)))->values()
        );
    }

    /** Les départs possibles, un jour donné, pour reporter un billet. */
    public function creneauxDeReport(Request $request, string $reference)
    {
        $billet = $request->user()->billets()->where('reference', $reference)->firstOrFail();
        $donnees = $request->validate(['date' => 'required|date']);

        $date = Carbon::parse($donnees['date'])->startOfDay();
        $route = $billet->route()->with('horaires')->firstOrFail();
        // $places = Billetterie::placesParDepart();
        $places = Billetterie::placesApp($route);

        $vendus = Billetterie::vendus($route->id, $date->toDateString());
        $heureActuelle = Billetterie::normaliserHeure((string) $billet->heure);

        $creneaux = collect(Billetterie::horairesDuJour($route, $date->toDateString()))
            ->map(fn (string $h) => [
                'heure' => str_replace(':', 'h', $h),
                'places' => max(0, $places - (int) $vendus->get($h, 0)),
                'passe' => $date->copy()->setTimeFromTimeString($h)->lte(now()),
                'actuel' => $date->equalTo($billet->date_voyage) && $h === $heureActuelle,
            ])
            ->values();

        return response()->json([
            'valide_jusqu_au' => $billet->valide_jusqu_au?->toDateString(),
            'creneaux' => $creneaux,
        ]);
    }

    /** Reporte le voyage à un autre jour, dans la durée de validité du billet (aucun motif demandé). */
    public function reporter(Request $request, string $reference)
    {
        $data = $request->validate([
            'date_voyage' => ['required', 'date'],
            'heure' => ['required', 'string', 'max:10'],
        ]);

        $billet = $request->user()->billets()->where('reference', $reference)->firstOrFail();

        try {
            $billet->reporter($request->user(), Carbon::parse($data['date_voyage']), $data['heure']);
        } catch (PlacesInsuffisantes $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'places_restantes' => $e->restantes,
            ], 409);
        } catch (\DomainException|AuthorizationException $e) {
            return $this->refus($e->getMessage());
        }

        $billet = $billet->fresh(['route.compagnie', 'route.villeArrivee', 'gare']);

        rescue(fn () => NotificationClient::pourBillet($billet, 'report', 'Voyage reporté', 'Votre voyage a été reporté :'));

        return response()->json(['success' => true, 'billet' => $this->formater($billet)]);
    }

    /**
     * Le client signale sa présence à la gare. C'est un signal d'aide pour l'agent,
     * pas une preuve : c'est l'agent qui décide de l'embarquement.
     */
    public function presence(Request $request, string $reference)
    {
        $data = $request->validate([
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $billet = $request->user()->billets()->where('reference', $reference)->with('gare')->firstOrFail();

        if ($billet->statut !== BilletStatut::Valide) {
            return $this->refus('Ce billet n\'est pas valide.');
        }

        $depart = $billet->date_voyage->copy()
            ->setTimeFromTimeString(Billetterie::normaliserHeure((string) $billet->heure));

        // Fenêtre : de 2 h avant à 30 min après le départ.
        if (now()->lt($depart->copy()->subHours(2)) || now()->gt($depart->copy()->addMinutes(30))) {
            return $this->refus('Ce n\'est pas encore l\'heure de ce départ.');
        }

        $gare = $billet->gare;
        $verifiee = false;

        if (isset($data['latitude'], $data['longitude']) && $gare?->latitude && $gare?->longitude) {
            $verifiee = $this->distanceMetres(
                (float) $data['latitude'], (float) $data['longitude'],
                (float) $gare->latitude, (float) $gare->longitude
            ) <= (int) config('billetterie.presence_rayon_m', 400);
        }

        $billet->forceFill(['presence_le' => now(), 'presence_verifiee' => $verifiee])->save();

        return response()->json(['success' => true, 'verifiee' => $verifiee]);
    }

    private function distanceMetres(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * 6371000 * asin(min(1, sqrt($a)));
    }

    /** Forme unique d'un billet pour l'application mobile. */
    private function formater(Billet $b, ?Collection $villes = null, bool $aAvis = false): array
    {
        $gare = $b->gare;
        $villes ??= Ville::whereKey($gare?->ville_id)->pluck('nom', 'id');

        return [
            'reference' => $b->reference,
            'statut' => $b->statut->value,
            'compagnie' => $b->route?->compagnie?->slug,
            'route_id' => $b->route_transport_id,
            'depart' => $villes[$gare?->ville_id] ?? '',
            'arrivee' => $b->route?->villeArrivee?->nom ?? '',
            'date_voyage' => $b->date_voyage->toDateString(),
            'heure' => $b->heure,
            'travelers_count' => $b->travelers_count,
            'montant_transport' => $b->montant_transport,
            'taxe' => $b->taxe,
            'operateur_paiement' => $b->operateur_paiement,
            'date_paiement' => $b->date_paiement?->toIso8601String(),
            'valide_jusqu_au' => $b->valide_jusqu_au?->toDateString(),
            'a_avis' => $aAvis,
            'voyageurs' => $b->voyageurs, // [{nom, code}] : codes officiels du serveur
            // 'prix_ticket' => $b->route?->prix,
            // 'duree_max' => $b->route?->duree_max,
            // 'gare' => $gare ? [
            // 'quartier' => $gare->quartier,
            // 'telephone' => $gare->telephone,
            // 'adresse' => $gare->adresse,
            // 'latitude' => $gare->latitude,
            // 'longitude' => $gare->longitude,
            // 'qr_token' => BilletToken::generer($billet),
            // ] : null,
            'prix_ticket' => $b->route?->prix,
            'duree_max' => $b->route?->duree_max,
            'qr_token' => BilletToken::generer($b),
            'gare' => $gare ? [
                'quartier' => $gare->quartier,
                'telephone' => $gare->telephone,
                'adresse' => $gare->adresse,
                'latitude' => $gare->latitude,
                'longitude' => $gare->longitude,
            ] : null,
        ];
    }

    private function refus(string $message, int $statut = 422, array $extra = [])
    {
        return response()->json(array_merge(['success' => false, 'message' => $message], $extra), $statut);
    }
}
