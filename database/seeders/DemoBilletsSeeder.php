<?php

namespace Database\Seeders;

use App\Enums\BilletStatut;
use App\Models\Compagnie;
use App\Models\RouteTransport;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Données FICTIVES pour voir le tableau de bord en situation.
 * Billets : références "DEMO-...". Clients : prénom "Demo", téléphone "990xxxxx".
 * Suppression : php artisan db:seed --class=SupprimerDonneesDemoSeeder
 */
class DemoBilletsSeeder extends Seeder
{
    private const JOURS = 60;            // historique généré (jours terminés)

    private const PART_DES_ROUTES = 0.15; // part des routes de chaque compagnie qui ont des ventes

    private const PLACES = 60;

    private const NB_CLIENTS = 250;

    private const TAXE_PAR_VOYAGEUR = 100; // valeur de démonstration

    private const OPERATEURS = ['Orange Money', 'Moov Money', 'Coris Money', 'Sank Money'];

    private const JOURS_ISO = ['lun' => 1, 'mar' => 2, 'mer' => 3, 'jeu' => 4, 'ven' => 5, 'sam' => 6, 'dim' => 7];

    private const FACTEUR_JOUR = [1 => 0.9, 2 => 0.8, 3 => 0.8, 4 => 0.9, 5 => 1.15, 6 => 1.0, 7 => 1.2];

    private const JOURS_FUTURS = 7;       // jours à venir avec des réservations

    private const PART_EN_ATTENTE = 0.06; // part des billets à venir en attente de paiement

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('Refusé : ce seeder crée des données fictives.');

            return;
        }

        if (DB::table('billets')->where('reference', 'like', 'DEMO-%')->exists()) {
            $this->command?->warn("Des billets de démonstration existent déjà. Lance d'abord : php artisan db:seed --class=SupprimerDonneesDemoSeeder");

            return;
        }

        if ($this->command && ! $this->command->confirm('Créer environ 50 000 billets FICTIFS dans la base '.config('database.connections.'.config('database.default').'.database').' ?')) {
            return;
        }

        mt_srand(42); // résultats reproductibles

        $routes = $this->routesRetenues();

        if ($routes === []) {
            $this->command?->warn("Aucune route : lance d'abord ReseauTransportSeeder.");

            return;
        }

        $clients = $this->creerClients();
        $horaires = DB::table('horaires')->whereIn('route_transport_id', array_keys($routes))->get()->groupBy('route_transport_id');

        $tampon = [];
        $total = 0;
        $numero = 1;
        $aujourdhui = now()->startOfDay();

        // for ($decalage = self::JOURS; $decalage >= 1; $decalage--) {
        //     $jour = $aujourdhui->copy()->subDays($decalage);
        //     $iso = $jour->dayOfWeekIso;
        //     $tendance = 0.7 + 0.3 * (1 - $decalage / self::JOURS);

        //     foreach ($routes as $routeId => $route) {
        //         foreach ($horaires->get($routeId, collect()) as $horaire) {
        //             if (! $this->jourAutorise($horaire->jours_semaine, $iso)) {
        //                 continue;
        //             }

        //             // Quelques départs restent vides.
        //             if (mt_rand(1, 100) <= 8) {
        //                 continue;
        //             }

        //             $taux = $route['popularite'] * self::FACTEUR_JOUR[$iso] * $tendance * (0.6 + 0.6 * $this->hasard());
        //             $voyageurs = (int) round(self::PLACES * min(0.98, max(0.03, $taux)));

        //             while ($voyageurs > 0) {
        //                 $groupe = min($voyageurs, $this->tailleGroupe());
        //                 $voyageurs -= $groupe;

        //                 $tampon[] = $this->ligneBillet($numero++, $route, $horaire, $jour, $groupe, $clients);

        //                 if (count($tampon) >= 1000) {
        //                     DB::table('billets')->insert($tampon);
        //                     $total += count($tampon);
        //                     $tampon = [];
        //                 }
        //             }
        //         }
        //     }
        // }

        $maintenant = now();

        for ($decalage = self::JOURS; $decalage >= -self::JOURS_FUTURS; $decalage--) {
            $jour = $aujourdhui->copy()->subDays($decalage);
            $iso = $jour->dayOfWeekIso;
            $tendance = 0.7 + 0.3 * (1 - max($decalage, 0) / self::JOURS);

            // Les jours à venir ne sont pas encore pleins : plus c'est loin, moins il y a de réservations.
            $remplissage = $decalage >= 1 ? 1.0 : max(0.15, 0.85 - 0.12 * (-$decalage));

            foreach ($routes as $routeId => $route) {
                foreach ($horaires->get($routeId, collect()) as $horaire) {
                    if (! $this->jourAutorise($horaire->jours_semaine, $iso)) {
                        continue;
                    }

                    // Quelques départs restent vides.
                    if (mt_rand(1, 100) <= 8) {
                        continue;
                    }

                    $taux = $route['popularite'] * self::FACTEUR_JOUR[$iso] * $tendance * $remplissage * (0.6 + 0.6 * $this->hasard());
                    $voyageurs = (int) round(self::PLACES * min(0.98, max(0.03, $taux)));

                    // Le départ a-t-il déjà eu lieu ?
                    $parti = $jour->copy()->setTimeFromTimeString(substr((string) $horaire->heure, 0, 5))->lessThan($maintenant);

                    while ($voyageurs > 0) {
                        $groupe = min($voyageurs, $this->tailleGroupe());
                        $voyageurs -= $groupe;

                        $tampon[] = $this->ligneBillet($numero++, $route, $horaire, $jour, $groupe, $clients, $parti);

                        if (count($tampon) >= 1000) {
                            DB::table('billets')->insert($tampon);
                            $total += count($tampon);
                            $tampon = [];
                        }
                    }
                }
            }
        }
        // fin {bloc {for a remplacer directement la dedans }}{}

        if ($tampon !== []) {
            DB::table('billets')->insert($tampon);
            $total += count($tampon);
        }

        $this->command?->info("Billets fictifs créés : {$total} sur ".count($routes).' routes, '.self::JOURS.' jours, '.count($clients).' clients.');
    }

    // /** @return array<int, array{compagnie_id:int, gare_id:int, prix:int, popularite:float}> */
    // private function routesRetenues(): array
    // {
    //     $retenues = [];

    //     foreach (Compagnie::orderBy('id')->get() as $compagnie) {
    //         $routes = RouteTransport::where('compagnie_id', $compagnie->id)->orderBy('id')->get();

    //         if ($routes->isEmpty()) {
    //             continue;
    //         }

    //         $nombre = max(1, (int) ceil($routes->count() * self::PART_DES_ROUTES));

    //         foreach ($routes->shuffle()->take($nombre) as $route) {
    //             $retenues[$route->id] = [
    //                 'compagnie_id' => $route->compagnie_id,
    //                 'gare_id' => $route->gare_depart_id,
    //                 'prix' => $route->prix,
    //                 'popularite' => 0.2 + 0.6 * $this->hasard(),
    //             ];
    //         }
    //     }

    //     return $retenues;
    // }
    /** @return array<int, array{compagnie_id:int, gare_id:int, prix:int, popularite:float}> */
    private function routesRetenues(): array
    {
        $retenues = [];

        $retenir = function (RouteTransport $route) use (&$retenues) {
            $retenues[$route->id] = [
                'compagnie_id' => $route->compagnie_id,
                'gare_id' => $route->gare_depart_id,
                'prix' => $route->prix,
                'popularite' => 0.2 + 0.6 * $this->hasard(),
            ];
        };

        foreach (Compagnie::orderBy('id')->get() as $compagnie) {
            $routes = RouteTransport::where('compagnie_id', $compagnie->id)->orderBy('id')->get();

            if ($routes->isEmpty()) {
                continue;
            }

            $nombre = max(1, (int) ceil($routes->count() * self::PART_DES_ROUTES));

            foreach ($routes->shuffle()->take($nombre) as $route) {
                $retenir($route);
            }
        }

        // Chaque gare garde au moins une route avec des ventes (pour tester les comptes agents).
        $garesCouvertes = collect($retenues)->pluck('gare_id')->unique()->all();

        foreach (RouteTransport::orderBy('id')->get()->groupBy('gare_depart_id') as $gareId => $routesGare) {
            if (! in_array($gareId, $garesCouvertes, true)) {
                $retenir($routesGare->first());
            }
        }

        return $retenues;
    }

    /** @return array<int, int> identifiants des clients fictifs */
    private function creerClients(): array
    {
        $mot_de_passe = Hash::make('demo-'.bin2hex(random_bytes(8))); // un seul hachage pour tous
        $maintenant = now();
        $lignes = [];

        for ($i = 1; $i <= self::NB_CLIENTS; $i++) {
            $lignes[] = [
                'prenom' => 'Demo',
                'nom' => 'Client'.$i,
                'telephone' => '990'.str_pad((string) $i, 5, '0', STR_PAD_LEFT),
                'telephone_verifie' => true,
                'password' => $mot_de_passe,
                'created_at' => $maintenant,
                'updated_at' => $maintenant,
            ];
        }

        DB::table('users')->insert($lignes);

        return DB::table('users')->where('prenom', 'Demo')->where('telephone', 'like', '990%')->orderBy('id')->pluck('id')->all();
    }

    // private function ligneBillet(int $numero, array $route, object $horaire, $jour, int $groupe, array $clients): array
    // {
    //     // Les premiers clients achètent beaucoup plus souvent que les derniers.
    //     $client = $clients[(int) (count($clients) * ($this->hasard() ** 2))] ?? $clients[0];

    //     $datePaiement = $jour->copy()->subDays(mt_rand(0, 3))->setTime(mt_rand(6, 21), mt_rand(0, 59));

    //     return [
    //         'reference' => 'DEMO-' . str_pad((string) $numero, 7, '0', STR_PAD_LEFT),
    //         'user_id' => $client,
    //         'route_transport_id' => $horaire->route_transport_id,
    //         'gare_id' => $route['gare_id'],
    //         'date_voyage' => $jour->toDateString(),
    //         'heure' => substr((string) $horaire->heure, 0, 5),
    //         'travelers_count' => $groupe,
    //         'montant_transport' => $route['prix'] * $groupe,
    //         'taxe' => self::TAXE_PAR_VOYAGEUR * $groupe,
    //         'operateur_paiement' => self::OPERATEURS[mt_rand(0, count(self::OPERATEURS) - 1)],
    //         'voyageurs' => json_encode(array_map(
    //             fn ($i) => ['nom' => "Voyageur {$i}", 'code' => (string) mt_rand(100000, 999999)],
    //             range(1, $groupe)
    //         )),
    //         'date_paiement' => $datePaiement,
    //         'created_at' => $datePaiement,
    //         'updated_at' => $datePaiement,
    //     ];
    // }

    private function ligneBillet(int $numero, array $route, object $horaire, $jour, int $groupe, array $clients, bool $parti): array
    {
        // Les premiers clients achètent beaucoup plus souvent que les derniers.
        $client = $clients[(int) (count($clients) * ($this->hasard() ** 2))] ?? $clients[0];

        $statut = $this->tirerStatut($parti);

        if ($statut === BilletStatut::EnAttentePaiement) {
            // Commande démarrée il y a quelques minutes, pas encore payée.
            $creation = now()->subMinutes(mt_rand(1, 120));
            $datePaiement = null;
            $operateur = null;
        } else {
            $creation = $datePaiement = $jour->copy()->subDays(mt_rand(0, 3))->setTime(mt_rand(6, 21), mt_rand(0, 59));

            // Un paiement ne peut pas être dans le futur (billets du jour et des jours à venir).
            if ($datePaiement->isFuture()) {
                $creation = $datePaiement = now()->subMinutes(mt_rand(5, 720));
            }

            $operateur = self::OPERATEURS[mt_rand(0, count(self::OPERATEURS) - 1)];
        }

        return [
            'reference' => 'DEMO-'.str_pad((string) $numero, 7, '0', STR_PAD_LEFT),
            'user_id' => $client,
            'route_transport_id' => $horaire->route_transport_id,
            'gare_id' => $route['gare_id'],
            'date_voyage' => $jour->toDateString(),
            'heure' => substr((string) $horaire->heure, 0, 5),
            'travelers_count' => $groupe,
            'montant_transport' => $route['prix'] * $groupe,
            'taxe' => self::TAXE_PAR_VOYAGEUR * $groupe,
            'operateur_paiement' => $operateur,
            'voyageurs' => json_encode(array_map(
                fn ($i) => ['nom' => "Voyageur {$i}", 'code' => (string) mt_rand(100000, 999999)],
                range(1, $groupe)
            )),
            'statut' => $statut->value,
            'date_paiement' => $datePaiement,
            'created_at' => $creation,
            'updated_at' => $creation,
        ];
    }

    private function tirerStatut(bool $parti): BilletStatut
    {
        $tirage = mt_rand(1, 100);

        if ($parti) {
            // Départ déjà passé : surtout utilisés, quelques remboursés/annulés, quelques absents (restés valides).
            return match (true) {
                $tirage <= 90 => BilletStatut::Utilise,
                $tirage <= 94 => BilletStatut::Rembourse,
                $tirage <= 97 => BilletStatut::Annule,
                default => BilletStatut::Valide,
            };
        }

        // Départ à venir (plus tard aujourd'hui ou les jours suivants)
        if (mt_rand(1, 1000) <= self::PART_EN_ATTENTE * 1000) {
            return BilletStatut::EnAttentePaiement;
        }

        return match (true) {
            $tirage <= 94 => BilletStatut::Valide,
            $tirage <= 97 => BilletStatut::Annule,
            default => BilletStatut::Rembourse,
        };
    }

    // fin de la fonction ligne billet maintenant
    private function jourAutorise(?string $json, int $iso): bool
    {
        $liste = $json ? json_decode($json, true) : null;

        if (! is_array($liste) || $liste === []) {
            return true;
        }

        foreach ($liste as $jour) {
            if ((self::JOURS_ISO[mb_substr(mb_strtolower((string) $jour), 0, 3)] ?? null) === $iso) {
                return true;
            }
        }

        return false;
    }

    private function tailleGroupe(): int
    {
        $tirage = mt_rand(1, 100);

        return $tirage <= 60 ? 1 : ($tirage <= 85 ? 2 : ($tirage <= 95 ? 3 : 4));
    }

    private function hasard(): float
    {
        return mt_rand() / mt_getrandmax();
    }
}
