<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Compagnie;
use App\Models\Ville;
use App\Services\Billetterie;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class RouteTransportController extends Controller
{
    public function index(Request $request, Compagnie $compagnie)
    {
        if (! $compagnie->actif) {
            return response()->json([]);
        }

        $donnees = $request->validate([
            'depart' => 'required|string|max:100',
            'date' => 'nullable|date',
        ]);

        $depart = trim($donnees['depart']);

        $ville = Ville::where('nom', $depart)
            ->orWhereJsonContains('aliases', mb_strtolower($depart))
            ->first();

        if (! $ville) {
            return response()->json([]);
        }

        $date = isset($donnees['date']) ? Carbon::parse($donnees['date'])->toDateString() : null;
        $places = Billetterie::placesParDepart();
        $enHHhMM = fn (string $heure) => str_replace(':', 'h', $heure);

        $routes = $compagnie->routes()
            ->where('actif', true)
            ->whereHas('gareDepart', fn ($q) => $q->where('ville_id', $ville->id))
            ->with(['gareDepart', 'villeArrivee', 'horaires'])
            ->get();

        return response()->json(
            $routes->groupBy('villeArrivee.nom')->map(function ($groupe) use ($date, $places, $enHHhMM) {
                return [
                    'destination' => $groupe->first()->villeArrivee->nom,
                    'gares' => $groupe->map(function ($r) use ($date, $places, $enHHhMM) {
                        $horaires = $date
                            ? Billetterie::horairesDuJour($r, $date)
                            : $r->horaires->map(fn ($h) => Billetterie::normaliserHeure((string) $h->heure))->unique()->sort()->values()->all();

                        $gare = [
                            'route_id' => $r->id,
                            'gare_id' => $r->gare_depart_id,
                            'quartier' => $r->gareDepart->quartier,
                            'horaires' => array_map($enHHhMM, $horaires),
                            'prixTicket' => $r->prix,
                            'dureeMax' => $r->duree_max,
                            'telephone' => $r->gareDepart->telephone,
                            'adresse' => $r->gareDepart->adresse,
                            'latitude' => $r->gareDepart->latitude,
                            'longitude' => $r->gareDepart->longitude,
                        ];

                        if ($date) {
                            $vendus = Billetterie::vendus($r->id, $date);

                            $gare['disponibilites'] = collect($horaires)
                                ->mapWithKeys(fn ($h) => [$enHHhMM($h) => max(0, $places - (int) $vendus->get($h, 0))])
                                ->all();
                        }

                        return $gare;
                    })->values(),
                ];
            })->values()
        );
    }
}