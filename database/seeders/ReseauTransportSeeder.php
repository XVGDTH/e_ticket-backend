<?php

namespace Database\Seeders;

use App\Models\Compagnie;
use App\Models\Gare;
use App\Models\Horaire;
use App\Models\RouteTransport;
use App\Models\Ville;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReseauTransportSeeder extends Seeder
{
    /** Clé de ville de départ (application mobile) => nom affiché. */
    private const VILLES_DEPART = [
        'ouagadougou' => 'Ouagadougou',
        'bobo-dioulasso' => 'Bobo-Dioulasso',
        "fada n'gourma" => "Fada N'Gourma",
        'banfora' => 'Banfora',
    ];

    /** @var array<string, Ville> */
    private array $villes = [];

    private array $compteurs = ['compagnies' => 0, 'villes' => 0, 'gares' => 0, 'routes' => 0, 'horaires' => 0];

    public function run(): void
    {
        foreach (Ville::all() as $ville) {
            $this->villes[$this->cle($ville->nom)] = $ville;
        }

        DB::transaction(function () {
            foreach ($this->donnees() as $nomCompagnie => $departs) {
                $compagnie = $this->compagnie($nomCompagnie);

                foreach ($departs as $cleVille => $routes) {
                    $villeDepart = $this->ville(self::VILLES_DEPART[$cleVille] ?? $cleVille);

                    foreach ($routes as [$destination, $quartier, $horaires, $prix, $duree, $telephone, $adresse]) {
                        $gare = $this->gare($compagnie, $villeDepart, $quartier, $telephone, $adresse);
                        $villeArrivee = $this->ville($destination);

                        $route = RouteTransport::firstOrCreate(
                            [
                                'compagnie_id' => $compagnie->id,
                                'gare_depart_id' => $gare->id,
                                'ville_arrivee_id' => $villeArrivee->id,
                            ],
                            ['prix' => $prix, 'duree_max' => $duree, 'actif' => true]
                        );

                        if ($route->wasRecentlyCreated) {
                            $this->compteurs['routes']++;
                        }

                        foreach ($horaires as $horaire) {
                            $creneau = Horaire::firstOrCreate([
                                'route_transport_id' => $route->id,
                                'heure' => str_replace('h', ':', $horaire) . ':00',
                            ]);

                            if ($creneau->wasRecentlyCreated) {
                                $this->compteurs['horaires']++;
                            }
                        }
                    }
                }
            }
        });

        $this->command?->info(sprintf(
            'Créés : %d compagnie(s), %d ville(s), %d gare(s), %d route(s), %d horaire(s).',
            $this->compteurs['compagnies'],
            $this->compteurs['villes'],
            $this->compteurs['gares'],
            $this->compteurs['routes'],
            $this->compteurs['horaires'],
        ));
    }

    // ---------- Outils ----------

    private function cle(string $texte): string
    {
        return preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii($texte)));
    }

    private function compagnie(string $nom): Compagnie
    {
        $compagnie = Compagnie::whereRaw('LOWER(nom) = ?', [mb_strtolower($nom)])->first();

        if ($compagnie) {
            return $compagnie;
        }

        $base = Str::slug($nom);
        $slug = $base;
        $i = 2;

        while (Compagnie::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        $this->compteurs['compagnies']++;

        return Compagnie::create(['nom' => $nom, 'slug' => $slug, 'actif' => true]);
    }

    private function ville(string $nom): Ville
    {
        $cle = $this->cle($nom);

        if (! isset($this->villes[$cle])) {
            $this->compteurs['villes']++;
            $this->villes[$cle] = Ville::create([
                'nom' => $nom,
                'aliases' => array_values(array_unique([mb_strtolower($nom), Str::lower(Str::ascii($nom))])),
            ]);
        }

        return $this->villes[$cle];
    }

    private function gare(Compagnie $compagnie, Ville $ville, string $quartier, ?string $telephone, ?string $adresse): Gare
    {
        $gare = Gare::where('compagnie_id', $compagnie->id)->where('quartier', $quartier)->first();

        if (! $gare) {
            $this->compteurs['gares']++;

            return Gare::create([
                'compagnie_id' => $compagnie->id,
                'ville_id' => $ville->id,
                'quartier' => $quartier,
                'telephone' => $telephone,
                'adresse' => $adresse,
            ]);
        }

        // Une même gare peut avoir un numéro différent selon la route : on les garde tous.
        $numeros = array_values(array_filter(array_map('trim', explode('/', (string) $gare->telephone))));

        if ($telephone && ! in_array($telephone, $numeros, true)) {
            $numeros[] = $telephone;
            $gare->telephone = implode(' / ', $numeros);
        }

        if ($adresse && ! $gare->adresse) {
            $gare->adresse = $adresse;
        }

        if ($gare->isDirty()) {
            $gare->save();
        }

        return $gare;
    }

    // ---------- Données ----------
    // Chaque route : [destination, gare de départ, horaires, prix, durée max, téléphone, adresse]

    private function donnees(): array
    {
        $h = fn (string $liste): array => explode(' ', $liste);

        $patteDoie = "Gare de Paglayiri (Patte d'Oie)";
        $fada = "Gare de Fada N'Gourma";
        $bobo = $h('06h00 07h00 08h30 10h15 12h00 13h30 14h00 16h00 16h30 18h00 19h30 21h00');
        $elitis = $h('08h30 10h30 14h30 18h30');
        $rakBobo = $h('05h30 07h30 08h30 09h30 10h30 13h30 14h30 17h00 18h00 19h00');
        $rakBanfora = $h('12h30 18h00 20h00');
        $rakNiangoloko = $h('06h30 07h30 08h30 09h30 10h30 20h00');
        $saramayaOuaga = $h('08h30 09h30 10h30 12h30 14h30 18h30 22h30 23h45');
        $tampouy = $h('06h00 10h00 14h00 16h00');
        $larle = $h('06h00 10h30 15h00');
        $adressePissy = 'Sur le goudron du terrain omnisports de Pissy, vers la Sonabhy, juste après le CMA de Pissy, à droite';
        $adresseZad = 'Après la pédiatrie vers Ouaga 2000, au feu après la Maison Artisanale, à droite puis à gauche';
        $adresseSaaba = 'Du jardin triangle vers la station OTAME, 200 m après la station Shell, à droite';

        return [
            'TSR' => [
                'ouagadougou' => [
                    ['Bobo-Dioulasso', 'Gare de Wemtenga', $h('06h00 07h20 08h20 09h20 10h20 12h20 13h20 14h20 16h00 17h20 19h20 22h00'), 7000, '5h30', '72 04 86 45', null],
                    ['Gaoua', 'Gare de Wemtenga', $h('07h00 08h00 09h00 10h20 13h20 15h00 17h00'), 8000, '5h00', '72 04 86 45', null],
                    ['Manga', $patteDoie, $h('06h00 10h00 13h00 17h30'), 1500, '1h30', '72 90 97 31', null],
                    ['Zabré', $patteDoie, $h('07h00 11h00 16h00 18h00'), 2000, '2h00', '72 90 97 31', null],
                    ['Léo', $patteDoie, $h('07h20 09h20 11h20 12h50 14h20 16h20'), 3000, '2h30', '72 90 97 31', null],
                    ['Diapaga', "Gare de l'Est", $h('06h00 08h00'), 5000, '4h30', '70 25 31 38', null],
                    ['Kongoussi', 'Gare de Wemtenga', $h('06h30 08h00 11h00 13h00 15h00 17h00'), 3000, '2h00', '70 25 31 38', null],
                    ['Dori', 'Gare de Wemtenga', $h('05h30 06h00 08h00 12h00 14h00'), 6000, '3h30', '70 25 31 38', null],
                    ['Dédougou', 'Gare de Wemtenga', $h('07h00 14h30'), 5000, '3h00', '70 25 31 38', null],
                    ['Koudougou', 'Gare de Wemtenga', $h('06h00 08h30 09h30 11h30 13h30 15h30 17h30'), 2500, '2h00', '70 25 31 38', null],
                ],
                'bobo-dioulasso' => [
                    ['Banfora', 'Gare de Bobo', $h('07h30 08h00 10h30 15h30 16h00 18h00'), 2500, '1h30', '70 70 52 32', null],
                ],
                "fada n'gourma" => [
                    ['Bobo-Dioulasso', $fada, $h('06h00'), 11000, '8h00', '53 16 49 01', null],
                    ['Kaya', $fada, $h('16h00'), 7000, '4h00', '53 16 49 01', null],
                    ['Koupéla', $fada, $h('06h00'), 2000, '1h30', '53 16 49 01', null],
                    ['Ouagadougou', $fada, $h('13h30'), 5000, '3h00', '53 16 49 01', null],
                    ['Ouahigouya', $fada, $h('08h00'), 8000, '6h00', '53 16 49 01', null],
                    ['Kongoussi', $fada, $h('08h00'), 8000, '5h00', '53 16 49 01', null],
                    ['Tenkodogo', $fada, $h('16h00'), 3500, '2h30', '53 16 49 01', null],
                ],
            ],

            'Rakieta' => [
                'ouagadougou' => [
                    ['Bobo-Dioulasso', 'Escale Pissy', $rakBobo, 7500, '5h30', '73 42 00 28', $adressePissy],
                    ['Bobo-Dioulasso', 'Escale ZAD', $rakBobo, 7500, '5h30', '73 42 00 71', $adresseZad],
                    ['Bobo-Dioulasso', 'Escale Saaba', $rakBobo, 7500, '5h30', '70 32 81 22', $adresseSaaba],
                    ['Banfora', 'Escale Pissy', $rakBanfora, 9000, '4h00', '73 42 00 28', null],
                    ['Banfora', 'Escale ZAD', $rakBanfora, 9000, '4h00', '73 42 00 71', null],
                    ['Banfora', 'Escale Saaba', $rakBanfora, 9000, '4h00', '70 32 81 22', null],
                    ['Niangoloko', 'Escale Pissy', $rakNiangoloko, 9500, '4h30', '73 42 00 28', null],
                    ['Niangoloko', 'Escale ZAD', $rakNiangoloko, 9500, '4h30', '73 42 00 71', null],
                    ['Niangoloko', 'Escale Saaba', $rakNiangoloko, 9500, '4h30', '70 32 81 22', null],
                    ['Pô', 'Oscar Yaar - Pô', $h('07h00 10h00 12h30 16h30'), 2500, '2h00', '62 11 61 18', null],
                    ['Pô', "Patte d'Oie - Pô", $h('07h15 10h15 12h45 16h45'), 2500, '2h00', '73 42 00 27', null],
                ],
                'bobo-dioulasso' => [
                    ['Ouagadougou', 'Gare centrale Bobo-Dioulasso', $h('07h00 10h00 14h00 18h30 22h30'), 7500, '5h30', '77 05 64 64', null],
                    ['Banfora', 'Gare centrale Bobo-Dioulasso', $bobo, 2500, '1h30', '77 05 64 64', null],
                    ['Ouahigouya', 'Gare centrale Bobo-Dioulasso', $h('14h30'), 11000, '6h00', '77 05 64 64', null],
                    ['Houndé', 'Gare centrale Bobo-Dioulasso', $h('14h30'), 2500, '1h30', '77 05 64 64', null],
                    ['Pa', 'Gare centrale Bobo-Dioulasso', $h('14h30'), 3000, '2h00', '77 05 64 64', null],
                    ['Dano', 'Gare centrale Bobo-Dioulasso', $h('14h30'), 3000, '2h30', '77 05 64 64', null],
                    ['Ouessa', 'Gare centrale Bobo-Dioulasso', $h('14h30'), 4500, '3h00', '77 05 64 64', null],
                    ['Hamélé', 'Gare centrale Bobo-Dioulasso', $h('14h30'), 4500, '3h00', '77 05 64 64', null],
                ],
                'banfora' => [
                    ['Ouagadougou', 'Gare centrale Banfora', $h('06h00 08h15 12h00 16h00 21h00'), 9000, '4h00', '77 05 64 63', null],
                    ['Bobo-Dioulasso', 'Gare centrale Banfora', $bobo, 2500, '1h30', '77 05 64 63', null],
                ],
            ],

            'CTROF' => [
                'bobo-dioulasso' => [
                    ['Ouagadougou', 'Gare CTROF Bobo-Dioulasso 1', $h('06h45 09h15 12h15 16h15 18h15 22h30 23h00'), 7000, '5h30', 'Guichet 51 02 60 60', null],
                    ['Ouagadougou', 'Gare CTROF Bobo-Dioulasso 2', $h('06h45 09h15 12h15 16h15 18h15 22h30 23h00'), 7000, '5h30', 'Guichet 51 50 12 12', null],
                ],
            ],

            'Elitis' => [
                'ouagadougou' => [
                    ['Bobo-Dioulasso', 'Gare Elitis Express (Sanyiri)', $elitis, 10000, '5h00', '70 21 05 21', null],
                    ['Bobo-Dioulasso', 'Escale Elitis Express (Pissy)', $elitis, 10000, '4h45', '70 21 05 21', null],
                ],
                'bobo-dioulasso' => [
                    ['Ouagadougou', 'Gare Elitis Express (Zone Résidentielle B)', $elitis, 10000, '5h00', '25 40 33 40', null],
                ],
            ],

            'Rahimo' => [
                'ouagadougou' => [
                    ['Bobo-Dioulasso', 'Gare Rahimo Kalgondin (Prestige)', $h('10h30 14h30 23h30'), 8000, '5h30', '64 86 37 55', 'Rue 30.73, Kalgondin'],
                    ['Bobo-Dioulasso', 'Gare Rahimo Tanghin (Classic)', $h('10h00 12h30 14h30 23h30'), 7500, '5h30', '07 21 03 73', 'Tanghin, Ouagadougou'],
                    ['Bobo-Dioulasso', 'Gare Rahimo Pissy (Escale)', $h('10h00 12h30 14h30 23h30'), 7500, '5h30', '07 21 03 73', 'Pissy, Ouagadougou'],
                ],
                'bobo-dioulasso' => [
                    ['Ouagadougou', 'Gare Rahimo Boulevard (Bobo)', $h('10h30 12h30 14h30 23h00 23h55'), 7500, '5h30', '64 86 37 56', 'Boulevard de la Révolution'],
                    ['Banfora', 'Gare Rahimo Boulevard (Bobo)', $h('06h00 08h30 12h30 15h30 18h00 20h30'), 1500, '1h30', '64 86 37 58', null],
                ],
            ],

            'Saramaya' => [
                'ouagadougou' => [
                    ['Bobo-Dioulasso', 'Gare de Gounghin', $saramayaOuaga, 6500, '5h30', '60 81 86 86', null],
                    ['Bobo-Dioulasso', 'Gare Charles de Gaulle', $saramayaOuaga, 6500, '5h30', '60 94 22 22', null],
                    ['Bobo-Dioulasso', 'Gare de Dassasgho', $h('07h45 08h45 11h45 13h45 17h30 21h45'), 6500, '5h30', '60 94 21 21', null],
                    ['Ouahigouya', 'Escale Tampouy', $tampouy, 3500, '2h30', '03 93 25 25', null],
                    ['Yako', 'Escale Tampouy', $tampouy, 2500, '1h30', '03 93 25 25', null],
                    ['Gourcy', 'Escale Tampouy', $tampouy, 3000, '2h00', '03 93 25 25', null],
                    ['Koudougou', 'Gare de Gounghin', $h('06h00 08h00 12h00 14h00 18h00'), 2500, '2h00', '50 97 74 74', null],
                ],
                'bobo-dioulasso' => [
                    ['Ouagadougou', 'Gare de Tounouma', $h('07h30 09h00 10h30 12h30 14h30 18h30 22h30 23h45'), 6500, '5h30', '60 81 89 89', 'Bd Félix Houphouët-Boigny, Tounouma'],
                ],
            ],

            'Staf' => [
                'ouagadougou' => [
                    ["Fada N'Gourma", 'Gare de Gounghin', $h('06h00 12h00 14h30'), 5000, '3h00', '25 40 77 77', null],
                    ['Bobo-Dioulasso', 'Gare de Gounghin', $h('05h00 07h00 09h00 11h00 13h00 15h00 17h00 19h00 21h00'), 7000, '5h30', '25 40 77 77', null],
                    ['Cinkansé', 'Gare de Gounghin', $h('07h00 13h00'), 6000, '2h30', '25 40 77 77', null],
                    ['Gaoua', 'Gare de Gounghin', $h('06h00 07h00 10h00 12h00 13h00 14h00 15h00 17h00'), 8500, '5h00', '25 40 77 77', null],
                    ['Kampti', 'Gare de Gounghin', $h('06h00 10h00 14h00 15h00 17h00'), 9000, '5h30', '25 40 77 77', null],
                    ['Ouahigouya', 'Gare de Larlé', $h('05h00 07h00 09h00 11h00 13h00 15h00 17h00 19h00 21h00'), 3500, '2h30', '70 73 93 22', null],
                    ['Gourcy', 'Gare de Larlé', $larle, 3000, '2h00', '70 73 93 22', null],
                    ['Yako', 'Gare de Larlé', $larle, 2500, '1h45', '70 73 93 22', null],
                    ['Boussé', 'Gare de Larlé', $larle, 2000, '1h15', '70 73 93 22', null],
                ],
            ],

            // TCV : seulement des tarifs internationaux dans l'application, donc aucune route à créer.
            'TCV' => [],
        ];
    }
}