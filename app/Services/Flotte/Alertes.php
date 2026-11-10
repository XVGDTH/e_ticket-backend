<?php

namespace App\Services\Flotte;

use App\Models\Chauffeur;
use App\Models\User;
use App\Models\Vehicule;

class Alertes
{
    /** @return array<int, array{compagnie_id:int, type:string, objet:string, document:string, date:?string, jours:?int, niveau:string}> */
    public static function pour(User $u): array
    {
        if (! Acces::visible($u)) {
            return [];
        }

        $limite = (int) config('flotte.alerte_jours', 30);
        $marge = (int) config('flotte.vidange_marge_km', 500);
        $items = [];

        foreach (Vehicule::query()->visiblePour($u)->where('statut', '!=', 'reforme')->get() as $v) {
            foreach ($v->documents() as $d) {
                if ($d['jours'] <= $limite) {
                    $items[] = [
                        'compagnie_id' => (int) $v->compagnie_id,
                        'type' => 'Car',
                        'objet' => $v->immatriculation,
                        'document' => $d['libelle'],
                        'date' => $d['date']->format('d/m/Y'),
                        'jours' => $d['jours'],
                        'niveau' => $d['jours'] < 0 ? 'danger' : 'warning',
                    ];
                }
            }

            if ($v->km_prochaine_vidange && $v->km_actuel >= $v->km_prochaine_vidange - $marge) {
                $items[] = [
                    'compagnie_id' => (int) $v->compagnie_id,
                    'type' => 'Car',
                    'objet' => $v->immatriculation,
                    'document' => 'Vidange ('.number_format($v->km_actuel, 0, ',', ' ').' km, prévue à '.number_format($v->km_prochaine_vidange, 0, ',', ' ').' km)',
                    'date' => null,
                    'jours' => null,
                    'niveau' => $v->km_actuel >= $v->km_prochaine_vidange ? 'danger' : 'warning',
                ];
            }
        }

        $chauffeurs = Chauffeur::query()->visiblePour($u)->where('actif', true)->whereNotNull('permis_expire_le')->get();

        foreach ($chauffeurs as $c) {
            $jours = Vehicule::jours(today(), $c->permis_expire_le);

            if ($jours <= $limite) {
                $items[] = [
                    'compagnie_id' => (int) $c->compagnie_id,
                    'type' => 'Chauffeur',
                    'objet' => $c->nomComplet(),
                    'document' => 'Permis de conduire',
                    'date' => $c->permis_expire_le->format('d/m/Y'),
                    'jours' => $jours,
                    'niveau' => $jours < 0 ? 'danger' : 'warning',
                ];
            }
        }

        usort($items, fn ($a, $b) => [$a['niveau'] === 'danger' ? 0 : 1, $a['jours'] ?? 0] <=> [$b['niveau'] === 'danger' ? 0 : 1, $b['jours'] ?? 0]);

        return $items;
    }

    public static function nombre(User $u): int
    {
        return count(self::pour($u));
    }
}
