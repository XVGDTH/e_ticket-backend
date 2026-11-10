<?php

namespace App\Services\Personnel;

use App\Models\CongeEmploye;
use App\Models\Employe;
use Carbon\Carbon;

class SoldeConges
{
    /**
     * Congés annuels de l'année civile : droit acquis (mois de service × jours par mois), pris et solde.
     *
     * @return array{droit: float, pris: int, solde: float}
     */
    public static function pour(Employe $e, ?int $annee = null): array
    {
        $annee ??= today()->year;
        $parMois = (float) config('personnel.conges.jours_par_mois', 2.5);

        $debutAnnee = Carbon::create($annee, 1, 1)->startOfDay();
        $finAnnee = Carbon::create($annee, 12, 31)->startOfDay();

        $depart = $e->date_embauche && $e->date_embauche->gt($debutAnnee) ? $e->date_embauche->copy() : $debutAnnee;
        $fin = $finAnnee->lt(today()) ? $finAnnee : today();

        $mois = $fin->lt($depart) ? 0 : (int) $depart->diffInMonths($fin);
        $droit = min(round($mois * $parMois, 1), round(12 * $parMois, 1));

        $pris = (int) CongeEmploye::query()
            ->where('employe_id', $e->id)->where('type', 'annuel')->where('statut', 'approuve')
            ->whereBetween('date_debut', [$debutAnnee->toDateString(), $finAnnee->toDateString()])
            ->sum('jours');

        return ['droit' => $droit, 'pris' => $pris, 'solde' => round($droit - $pris, 1)];
    }
}
