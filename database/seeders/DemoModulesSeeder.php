<?php

namespace Database\Seeders;

use App\Models\Compagnie;
use App\Models\ContratEmploye;
use App\Models\Employe;
use App\Models\PeriodePaie;
use App\Models\User;
use App\Services\ControleGestion\Reporting;
use App\Services\Personnel\CalculPaie;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DemoModulesSeeder extends Seeder
{
    private const COUNT = 100;

    /**
     * @var array<int, int>
     */
    private array $compagnieIds = [];

    /**
     * @var array<int, array<int, int>>
     */
    private array $garesParCompagnie = [];

    /**
     * @var array<int, array<int, int>>
     */
    private array $routesParCompagnie = [];

    /**
     * @var array<int, int>
     */
    private array $gareDepartParRoute = [];

    /**
     * @var array<int, int>
     */
    private array $vehiculeIds = [];

    /**
     * @var array<int, int>
     */
    private array $chauffeurIds = [];

    /**
     * @var array<int, int>
     */
    private array $voyageIds = [];

    /**
     * @var array<int, int>
     */
    private array $billetIds = [];

    /**
     * @var array<int, int>
     */
    private array $caisseIds = [];

    /**
     * @var array<int, int>
     */
    private array $colisIds = [];

    /**
     * @var array<int, array<int, int>>
     */
    private array $categoriesImmoParCompagnie = [];

    /**
     * @var array<int, array<int, int>>
     */
    private array $comptesParCompagnie = [];

    /**
     * @var array<int, array<int, int>>
     */
    private array $journauxParCompagnie = [];

    /**
     * @var array<int, array<int, int>>
     */
    private array $sectionsParCompagnie = [];

    /**
     * @var array<int, int>
     */
    private array $immobilisationIds = [];

    /**
     * @var array<int, array<int, int>>
     */
    private array $periodesPaieParCompagnie = [];

    /**
     * @var array<string, array<int, string>>
     */
    private array $colonnesParTable = [];

    public function run(): void
    {
        if (! in_array(app()->environment(), ['local', 'testing'], true)) {
            $this->command?->error('Refusé : les données de démonstration sont limitées aux environnements local et testing.');

            return;
        }

        if (DB::table('ci_controles')->where('titre', 'like', 'DEMO-MOD-%')->exists()) {
            $this->command?->warn('Les données de démonstration des modules existent déjà. Aucune nouvelle ligne n’a été créée.');

            return;
        }

        $this->compagnieIds = Compagnie::query()
            ->where('actif', true)
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($this->compagnieIds === []) {
            $this->command?->error('Aucune compagnie active : créez une compagnie avant les données de démonstration.');

            return;
        }

        $acteur = User::query()->where('actif', true)->orderBy('id')->first();

        if (! $acteur) {
            $this->command?->error('Aucun utilisateur actif : impossible de rattacher les données de démonstration.');

            return;
        }

        DB::transaction(function () use ($acteur): void {
            $villes = $this->creerVilles();
            $this->creerGares($villes);
            $this->creerRoutes($villes);
            $this->creerFlotte($acteur->id);
            $this->creerGuichet($acteur->id);
            $this->creerColis($acteur->id);
            $this->creerAudit($acteur->id);
            $this->creerControleGestion($acteur->id);
            $this->creerPersonnel($acteur->id);
            $this->creerComptabilite($acteur->id);
        });

        $this->command?->info('Jeu de démonstration créé : 100 lignes par table métier, réparties entre les compagnies actives.');
    }

    /**
     * @return array<int, int>
     */
    private function creerVilles(): array
    {
        $ids = [];

        for ($i = 1; $i <= self::COUNT; $i++) {
            $ids[] = $this->ajouter('villes', [
                'nom' => sprintf('Ville Démo %03d', $i),
                'aliases' => json_encode([], JSON_THROW_ON_ERROR),
            ]);
        }

        return $ids;
    }

    /**
     * @param  array<int, int>  $villes
     */
    private function creerGares(array $villes): void
    {
        for ($i = 0; $i < self::COUNT; $i++) {
            $compagnieId = $this->compagnie($i);
            $this->garesParCompagnie[$compagnieId][] = $this->ajouter('gares', [
                'compagnie_id' => $compagnieId,
                'ville_id' => $villes[$i],
                'quartier' => sprintf('DEMO-MOD-GARE-%03d', $i + 1),
                'telephone' => sprintf('+226700%06d', $i + 1),
                'adresse' => sprintf('Adresse fictive %03d', $i + 1),
            ]);
        }
    }

    /**
     * @param  array<int, int>  $villes
     */
    private function creerRoutes(array $villes): void
    {
        for ($i = 0; $i < self::COUNT; $i++) {
            $compagnieId = $this->compagnie($i);
            $gares = $this->garesParCompagnie[$compagnieId];
            $gareDepartId = $gares[$this->indexCompagnie($i) % count($gares)];
            $routeId = $this->ajouter('route_transports', [
                'compagnie_id' => $compagnieId,
                'gare_depart_id' => $gareDepartId,
                'ville_arrivee_id' => $villes[($i + 1) % count($villes)],
                'prix' => 5000 + ($i * 250),
                'duree_max' => '06:00',
                'distance_km' => 50 + ($i * 7),
                'actif' => true,
            ]);
            $this->routesParCompagnie[$compagnieId][] = $routeId;
            $this->gareDepartParRoute[$routeId] = $gareDepartId;

            $this->ajouter('horaires', [
                'route_transport_id' => $routeId,
                'heure' => sprintf('%02d:%02d:00', 5 + ($i % 14), ($i % 4) * 15),
                'jours_semaine' => json_encode([1, 2, 3, 4, 5, 6, 7], JSON_THROW_ON_ERROR),
            ]);
        }
    }

    private function creerFlotte(int $acteurId): void
    {
        for ($i = 0; $i < self::COUNT; $i++) {
            $compagnieId = $this->compagnie($i);
            $gares = $this->garesParCompagnie[$compagnieId];
            $this->vehiculeIds[$i] = $this->ajouter('vehicules', [
                'compagnie_id' => $compagnieId,
                'gare_id' => $gares[$this->indexCompagnie($i) % count($gares)],
                'immatriculation' => sprintf('DEMO-MOD-%05d', $i + 1),
                'numero_parc' => sprintf('PARC-%04d', $i + 1),
                'marque' => 'Démo Motors',
                'modele' => 'Autocar',
                'annee' => 2018 + ($i % 7),
                'places' => 45 + ($i % 10),
                'carburant' => 'gasoil',
                'km_actuel' => 50000 + ($i * 1300),
                'statut' => $i % 10 === 0 ? 'maintenance' : 'actif',
                'date_acquisition' => today()->subYears(2)->toDateString(),
                'date_mise_en_service' => today()->subYears(2)->toDateString(),
                'prix_acquisition' => 45000000 + ($i * 100000),
                'duree_amortissement_ans' => 10,
                'valeur_residuelle' => 1000000,
                'assurance_expire_le' => today()->addMonths(6)->toDateString(),
                'visite_technique_expire_le' => today()->addMonths(3)->toDateString(),
                'km_prochaine_vidange' => 50000 + ($i * 1300) + 1000,
                'note' => 'Donnée fictive de démonstration.',
            ]);
        }

        for ($i = 0; $i < self::COUNT; $i++) {
            $compagnieId = $this->compagnie($i);
            $gares = $this->garesParCompagnie[$compagnieId];
            $this->chauffeurIds[$i] = $this->ajouter('chauffeurs', [
                'compagnie_id' => $compagnieId,
                'gare_id' => $gares[$this->indexCompagnie($i) % count($gares)],
                'prenom' => sprintf('Chauffeur%03d', $i + 1),
                'nom' => 'DEMO',
                'telephone' => sprintf('+226710%06d', $i + 1),
                'permis_numero' => sprintf('PERMIS-DEMO-%05d', $i + 1),
                'permis_categorie' => 'D',
                'permis_expire_le' => today()->addYears(2)->toDateString(),
                'date_embauche' => today()->subYears(1)->toDateString(),
                'remuneration_type' => 'par_voyage',
                'remuneration_montant' => 15000,
                'vehicule_habituel_id' => $this->vehiculeIds[$i],
                'actif' => true,
                'note' => 'Chauffeur fictif de démonstration.',
            ]);
        }

        for ($i = 0; $i < self::COUNT; $i++) {
            $compagnieId = $this->compagnie($i);
            $routes = $this->routesParCompagnie[$compagnieId];
            $routeId = $routes[$this->indexCompagnie($i) % count($routes)];
            $this->voyageIds[$i] = $this->ajouter('voyages_cars', [
                'compagnie_id' => $compagnieId,
                'route_transport_id' => $routeId,
                'gare_id' => $this->gareDepartParRoute[$routeId],
                'date_voyage' => today()->subDays($i)->toDateString(),
                'heure' => sprintf('%02d:%02d', 6 + ($i % 12), ($i % 4) * 15),
                'vehicule_id' => $this->vehiculeIds[$i],
                'chauffeur_id' => $this->chauffeurIds[$i],
                'statut' => $i % 3 === 0 ? 'termine' : 'programme',
                'km_parcourus' => 100 + ($i * 10),
                'places_guichet' => 25 + ($i % 20),
                'recette_guichet' => 150000 + ($i * 5000),
                'recette_colis' => 10000 + ($i * 1000),
                'observations' => 'Voyage fictif de démonstration.',
                'cree_par' => $acteurId,
            ]);

            $this->ajouter('depenses_vehicules', [
                'compagnie_id' => $compagnieId,
                'vehicule_id' => $this->vehiculeIds[$i],
                'voyage_id' => $this->voyageIds[$i],
                'date_depense' => today()->subDays($i)->toDateString(),
                'categorie' => $i % 3 === 0 ? 'carburant' : 'entretien',
                'montant' => 25000 + ($i * 1500),
                'litres' => $i % 3 === 0 ? 80 + $i : null,
                'kilometrage' => 50000 + ($i * 1300),
                'fournisseur' => sprintf('Fournisseur démo %03d', $i + 1),
                'reference' => sprintf('DEMO-MOD-DEP-%05d', $i + 1),
                'note' => 'Dépense fictive de démonstration.',
                'source' => 'saisie',
                'cree_par' => $acteurId,
            ]);
        }
    }

    private function creerGuichet(int $acteurId): void
    {
        for ($i = 0; $i < self::COUNT; $i++) {
            $compagnieId = $this->compagnie($i);
            $routes = $this->routesParCompagnie[$compagnieId];
            $routeId = $routes[$this->indexCompagnie($i) % count($routes)];
            $gareDepartId = $this->gareDepartParRoute[$routeId];
            $this->billetIds[$i] = $this->ajouter('billets', [
                'reference' => sprintf('DEMO-MOD-BIL-%05d', $i + 1),
                'user_id' => null,
                'route_transport_id' => $routeId,
                'gare_id' => $gareDepartId,
                'date_voyage' => today()->addDays($i % 30)->toDateString(),
                'heure' => sprintf('%02d:%02d', 6 + ($i % 12), ($i % 4) * 15),
                'travelers_count' => 1 + ($i % 4),
                'montant_transport' => 5000 + ($i * 250),
                'taxe' => 250,
                'operateur_paiement' => 'DEMO',
                'voyageurs' => json_encode([['nom' => sprintf('Voyageur%03d', $i + 1), 'code' => 'DEMO']], JSON_THROW_ON_ERROR),
                'date_paiement' => now()->subDays($i)->toDateTimeString(),
                'statut' => $i % 12 === 0 ? 'utilise' : 'valide',
                'canal' => 'guichet',
                'vendu_par' => $acteurId,
                'remise' => 0,
                'nom_client' => sprintf('Client démo %03d', $i + 1),
                'telephone_client' => sprintf('+226720%06d', $i + 1),
                'reference_paiement' => sprintf('DEMO-MOD-PAI-%05d', $i + 1),
                'impressions' => 1,
            ]);

            $this->ajouter('billet_evenements', [
                'billet_id' => $this->billetIds[$i],
                'statut_avant' => null,
                'statut_apres' => 'valide',
                'user_id' => $acteurId,
                'motif' => 'Billet fictif créé pour la démonstration.',
                'source' => 'panel',
            ]);

            $this->ajouter('verifications_billets', [
                'billet_id' => $this->billetIds[$i],
                'user_id' => $acteurId,
                'gare_id' => $gareDepartId,
                'mode' => 'manuel',
                'resultat' => 'valide',
                'contenu' => sprintf('DEMO-MOD-BIL-%05d', $i + 1),
            ]);

            $this->caisseIds[$i] = $this->ajouter('caisses', [
                'compagnie_id' => $compagnieId,
                'gare_id' => $gareDepartId,
                'user_id' => $acteurId,
                'ouverte_le' => now()->subDays($i)->startOfDay()->toDateTimeString(),
                'fermee_le' => now()->subDays($i)->endOfDay()->toDateTimeString(),
                'fond_initial' => 50000,
                'especes_attendues' => 100000 + ($i * 5000),
                'especes_comptees' => 100000 + ($i * 5000),
                'ecart' => 0,
                'statut' => 'fermee',
                'note' => 'Caisse fictive de démonstration.',
                'valide_par' => $acteurId,
                'valide_le' => now()->subDays($i)->endOfDay()->toDateTimeString(),
            ]);

            $this->ajouter('mouvements_caisse', [
                'caisse_id' => $this->caisseIds[$i],
                'compagnie_id' => $compagnieId,
                'gare_id' => $gareDepartId,
                'billet_id' => $this->billetIds[$i],
                'user_id' => $acteurId,
                'type' => 'vente',
                'mode' => 'especes',
                'montant' => 5000 + ($i * 250),
                'motif' => 'Vente fictive de démonstration.',
                'reference' => sprintf('DEMO-MOD-MVT-%05d', $i + 1),
                'created_at' => now()->subDays($i)->toDateTimeString(),
            ]);

            $this->ajouter('frais_regles', [
                'compagnie_id' => $compagnieId,
                'gare_id' => $gareDepartId,
                'route_transport_id' => $routeId,
                'trajet' => 'national',
                'prix_min' => 5000 + ($i * 250),
                'prix_max' => 10000 + ($i * 250),
                'mode' => 'fixe',
                'valeur' => 250 + ($i % 20) * 25,
                'frais_min' => 100,
                'frais_max' => 1000,
                'actif' => true,
                'note' => sprintf('Règle fictive %03d', $i + 1),
            ]);
        }
    }

    private function creerColis(int $acteurId): void
    {
        for ($i = 0; $i < self::COUNT; $i++) {
            $compagnieId = $this->compagnie($i);
            $gares = $this->garesParCompagnie[$compagnieId];
            $origine = $gares[$this->indexCompagnie($i) % count($gares)];
            $destination = $gares[($this->indexCompagnie($i) + 1) % count($gares)];

            $this->colisIds[$i] = $this->ajouter('colis', [
                'reference' => sprintf('DEMO-MOD-COL-%05d', $i + 1),
                'type' => $i % 4 === 0 ? 'courrier' : 'colis',
                'compagnie_id' => $compagnieId,
                'gare_origine_id' => $origine,
                'gare_destination_id' => $destination,
                'expediteur_nom' => sprintf('Expéditeur démo %03d', $i + 1),
                'expediteur_telephone' => sprintf('+226730%06d', $i + 1),
                'destinataire_nom' => sprintf('Destinataire démo %03d', $i + 1),
                'destinataire_telephone' => sprintf('+226740%06d', $i + 1),
                'nature' => $i % 4 === 0 ? 'Documents' : 'Marchandises',
                'description' => 'Envoi fictif de démonstration.',
                'nombre_pieces' => 1 + ($i % 5),
                'poids_kg' => 1.5 + ($i % 40),
                'valeur_declaree' => 10000 + ($i * 500),
                'montant' => 1500 + ($i * 100),
                'paiement' => $i % 2 === 0 ? 'expediteur' : 'destinataire',
                'paye_le' => now()->subDays($i)->toDateTimeString(),
                'statut' => $i % 10 === 0 ? 'livre' : 'enregistre',
                'code_retrait' => str_pad((string) ($i + 1), 6, '0', STR_PAD_LEFT),
                'cree_par' => $acteurId,
                'remis_a' => $i % 10 === 0 ? sprintf('Destinataire démo %03d', $i + 1) : null,
                'remis_le' => $i % 10 === 0 ? now()->subDays($i)->toDateTimeString() : null,
                'code_verifie' => $i % 10 === 0,
            ]);

            $this->ajouter('colis_evenements', [
                'colis_id' => $this->colisIds[$i],
                'statut' => $i % 10 === 0 ? 'livre' : 'enregistre',
                'user_id' => $acteurId,
                'gare_id' => $origine,
                'note' => 'Événement fictif de démonstration.',
                'created_at' => now()->subDays($i)->toDateTimeString(),
            ]);
        }
    }

    private function creerAudit(int $acteurId): void
    {
        $controles = [];

        for ($i = 0; $i < self::COUNT; $i++) {
            $compagnieId = $this->compagnie($i);
            $controles[$i] = $this->ajouter('ci_controles', [
                'compagnie_id' => $compagnieId,
                'titre' => sprintf('DEMO-MOD-%03d · Contrôle périodique', $i + 1),
                'objectif' => 'Vérifier la conformité des opérations de démonstration.',
                'procedure' => 'Contrôle fictif documenté pour l’aperçu du module.',
                'frequence' => 'mensuelle',
                'niveau_risque' => ['faible', 'moyen', 'eleve'][$i % 3],
                'responsable_id' => $acteurId,
                'prochaine_echeance' => today()->addDays($i + 1)->toDateString(),
                'actif' => true,
                'cree_par' => $acteurId,
            ]);

            $this->ajouter('ci_recommandations', [
                'compagnie_id' => $compagnieId,
                'controle_id' => $controles[$i],
                'titre' => sprintf('DEMO-MOD-%03d · Recommandation', $i + 1),
                'constat' => 'Échantillon fictif pour l’affichage du suivi des recommandations.',
                'action_requise' => 'Documenter la vérification et son résultat.',
                'priorite' => ['basse', 'normale', 'haute'][$i % 3],
                'statut' => $i % 4 === 0 ? 'terminee' : 'ouverte',
                'responsable_id' => $acteurId,
                'echeance' => today()->addDays($i + 1)->toDateString(),
                'resolution' => $i % 4 === 0 ? 'Exemple clôturé.' : null,
                'terminee_le' => $i % 4 === 0 ? now()->toDateTimeString() : null,
                'cree_par' => $acteurId,
            ]);

            $this->ajouter('ci_journal_parametres', [
                'evenement' => 'DEMO_CREATE',
                'entite' => 'DEMO-MOD',
                'entite_id' => $controles[$i],
                'compagnie_id' => $compagnieId,
                'compagnie_nom' => Compagnie::query()->whereKey($compagnieId)->value('nom'),
                'acteur_id' => $acteurId,
                'acteur_nom' => 'Utilisateur de démonstration',
                'cible_utilisateur_id' => $acteurId,
                'cible_utilisateur_nom' => 'Utilisateur de démonstration',
                'valeurs' => json_encode(['fictif' => true, 'index' => $i + 1], JSON_THROW_ON_ERROR),
                'enregistre_le' => now()->subDays($i)->toDateTimeString(),
            ]);
        }

        for ($i = 0; $i < self::COUNT; $i++) {
            $compagnieId = $this->compagnie($i);
            $this->ajouter('ci_sequences_documents', [
                'compagnie_id' => $compagnieId,
                'serie' => sprintf('D%04d', $i + 1),
                'annee' => (int) today()->year,
                'dernier_numero' => $i + 1,
            ]);
        }

        $fournisseurs = [];
        $articles = [];
        $receptions = [];
        $lignesReception = [];
        $factures = [];

        for ($i = 0; $i < self::COUNT; $i++) {
            $compagnieId = $this->compagnie($i);
            $fournisseurs[$i] = $this->ajouter('ci_fournisseurs', [
                'compagnie_id' => $compagnieId,
                'nom' => sprintf('DEMO-MOD Fournisseur %03d', $i + 1),
                'identifiant_fiscal' => sprintf('IFU-DEMO-%05d', $i + 1),
                'telephone' => sprintf('+226750%06d', $i + 1),
                'email' => sprintf('fournisseur%03d@example.test', $i + 1),
                'adresse' => 'Adresse fictive de démonstration.',
                'actif' => true,
                'cree_par' => $acteurId,
                'cree_par_nom' => 'Utilisateur de démonstration',
            ]);

            $articles[$i] = $this->ajouter('ci_articles_stock', [
                'compagnie_id' => $compagnieId,
                'reference' => sprintf('DEMO-MOD-ART-%05d', $i + 1),
                'nom' => sprintf('Article de stock fictif %03d', $i + 1),
                'unite' => 'unité',
                'quantite_stock' => 100 + $i,
                'cree_par' => $acteurId,
            ]);

            $receptions[$i] = $this->ajouter('ci_receptions_fournisseurs', [
                'compagnie_id' => $compagnieId,
                'fournisseur_id' => $fournisseurs[$i],
                'numero' => sprintf('DEMO-MOD-REC-%05d', $i + 1),
                'recu_le' => today()->subDays($i)->toDateString(),
                'description' => 'Réception fictive de démonstration.',
                'statut' => 'validee',
                'cree_par' => $acteurId,
                'cree_par_nom' => 'Utilisateur de démonstration',
                'valide_par' => $acteurId,
                'valide_par_nom' => 'Utilisateur de démonstration',
                'valide_le' => now()->subDays($i)->toDateTimeString(),
            ]);

            $lignesReception[$i] = $this->ajouter('ci_lignes_reception_fournisseur', [
                'reception_id' => $receptions[$i],
                'article_id' => $articles[$i],
                'quantite' => 1 + ($i % 25),
            ]);

            $this->ajouter('ci_mouvements_stock', [
                'compagnie_id' => $compagnieId,
                'article_id' => $articles[$i],
                'reception_id' => $receptions[$i],
                'ligne_reception_id' => $lignesReception[$i],
                'type' => 'entree',
                'quantite' => 1 + ($i % 25),
                'cree_par' => $acteurId,
                'cree_par_nom' => 'Utilisateur de démonstration',
                'cree_le' => now()->subDays($i)->toDateTimeString(),
            ]);

            $factures[$i] = $this->ajouter('ci_factures_fournisseurs', [
                'compagnie_id' => $compagnieId,
                'fournisseur_id' => $fournisseurs[$i],
                'reception_id' => $receptions[$i],
                'numero' => sprintf('DEMO-MOD-FAC-%05d', $i + 1),
                'reference_fournisseur' => sprintf('REF-DEMO-%05d', $i + 1),
                'date_facture' => today()->subDays($i)->toDateString(),
                'echeance' => today()->addDays(30)->toDateString(),
                'montant' => 10000 + ($i * 1000),
                'statut' => 'validee',
                'cree_par' => $acteurId,
                'cree_par_nom' => 'Utilisateur de démonstration',
                'valide_par' => $acteurId,
                'valide_par_nom' => 'Utilisateur de démonstration',
                'valide_le' => now()->subDays($i)->toDateTimeString(),
            ]);

            $this->ajouter('ci_paiements_fournisseurs', [
                'compagnie_id' => $compagnieId,
                'facture_id' => $factures[$i],
                'numero' => sprintf('DEMO-MOD-REG-%05d', $i + 1),
                'montant' => 10000 + ($i * 1000),
                'paye_le' => today()->subDays($i)->toDateString(),
                'mode' => 'virement',
                'reference' => sprintf('DEMO-MOD-BQ-%05d', $i + 1),
                'statut' => 'valide',
                'cree_par' => $acteurId,
                'cree_par_nom' => 'Utilisateur de démonstration',
                'valide_par' => $acteurId,
                'valide_par_nom' => 'Utilisateur de démonstration',
                'valide_le' => now()->subDays($i)->toDateTimeString(),
            ]);
        }
    }

    private function creerControleGestion(int $acteurId): void
    {
        foreach ($this->compagnieIds as $compagnieId) {
            DB::table('cg_parametres')->insertOrIgnore([
                'compagnie_id' => $compagnieId,
                'norme_l100' => 30,
                'prix_litre' => 750,
                'ecart_seuil_pct' => 10,
                'commission_paiement_pct' => 2,
                'created_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ]);
        }

        $budgets = [];
        $versions = [];

        foreach ($this->compagnieIds as $compagnieId) {
            $versions[$compagnieId] = (int) DB::table('cg_budgets')
                ->where('compagnie_id', $compagnieId)
                ->where('annee', today()->year)
                ->max('version');
        }

        for ($i = 0; $i < self::COUNT; $i++) {
            $compagnieId = $this->compagnie($i);
            $routes = $this->routesParCompagnie[$compagnieId];
            $version = ++$versions[$compagnieId];
            $budgets[$i] = $this->ajouter('cg_budgets', [
                'compagnie_id' => $compagnieId,
                'annee' => today()->year,
                'version' => $version,
                'libelle' => sprintf('Budget fictif version %d', $version),
                'statut' => 'valide',
                'remplissage_cible_pct' => 75,
                'cout_km_maintenance' => 150,
                'cout_km_pneus' => 75,
                'frais_route_depart' => 2500,
                'cout_horaire_equipage' => 5000,
                'fixes_car_mensuel' => 100000,
                'norme_l100' => 30,
                'prix_litre' => 750,
                'commission_paiement_pct' => 2,
                'note' => 'Données fictives de démonstration.',
                'valide_le' => now()->toDateTimeString(),
                'valide_par' => $acteurId,
                'cree_par' => $acteurId,
            ]);

            for ($mois = 1; $mois <= 12; $mois++) {
                $this->ajouter('cg_budget_lignes', [
                    'budget_id' => $budgets[$i],
                    'mois' => $mois,
                    'route_transport_id' => $routes[($this->indexCompagnie($i) + $mois - 1) % count($routes)],
                    'departs' => 10 + ($i % 20),
                    'km' => 1000 + ($i * 10),
                    'sieges' => 450 + ($i * 5),
                    'pax' => 300 + ($i * 3),
                    'recettes' => 500000 + ($i * 10000),
                    'commissions' => 10000 + ($i * 200),
                    'carburant' => 100000 + ($i * 5000),
                    'equipage' => 50000 + ($i * 2000),
                    'maintenance' => 25000 + ($i * 1000),
                    'pneus' => 15000 + ($i * 500),
                    'frais_route' => 20000 + ($i * 1000),
                    'fixes_car' => 100000,
                    'gare' => 30000,
                    'siege' => 25000,
                ]);
            }
        }

        for ($i = 0; $i < self::COUNT; $i++) {
            $compagnieId = $this->compagnie($i);
            $mois = 1 + ($i % 12);
            $annee = today()->year - intdiv($i, 12);
            $snapshot = Reporting::mois($compagnieId, $annee, $mois);

            $this->ajouter('cg_commentaires', [
                'compagnie_id' => $compagnieId,
                'annee' => $annee,
                'mois' => $mois,
                'cle' => sprintf('DEMO-%03d', $i + 1),
                'texte' => 'Commentaire fictif destiné à l’aperçu du contrôle de gestion.',
                'user_id' => $acteurId,
            ]);

            $this->ajouter('cg_clotures', [
                'compagnie_id' => $compagnieId,
                'annee' => $annee,
                'mois' => $mois,
                'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
                'cloture_par' => $acteurId,
                'cloture_le' => now()->subMonths($i)->toDateTimeString(),
            ]);

            $this->ajouter('cg_charges_structure', [
                'compagnie_id' => $compagnieId,
                'gare_id' => $this->garesParCompagnie[$compagnieId][$this->indexCompagnie($i) % count($this->garesParCompagnie[$compagnieId])],
                'niveau' => ['gare', 'siege', 'atelier'][$i % 3],
                'libelle' => sprintf('Charge fictive %03d', $i + 1),
                'montant' => 25000 + ($i * 1000),
                'periode_debut' => today()->subMonthNoOverflow()->startOfMonth()->toDateString(),
                'periode_fin' => today()->endOfMonth()->toDateString(),
                'cle' => ['departs', 'voyageurs', 'km', 'recettes'][$i % 4],
                'note' => 'Charge fictive de démonstration.',
                'cree_par' => $acteurId,
            ]);
        }
    }

    private function creerPersonnel(int $acteurId): void
    {
        $employes = [];
        $contrats = [];
        $avances = [];
        $localIndex = [];
        $effectifsParCompagnie = [];

        for ($i = 0; $i < self::COUNT; $i++) {
            $compagnieId = $this->compagnie($i);
            $localIndex[$i] = $effectifsParCompagnie[$compagnieId] ?? 0;
            $effectifsParCompagnie[$compagnieId] = $localIndex[$i] + 1;
        }

        foreach ($effectifsParCompagnie as $compagnieId => $effectif) {
            for ($local = 0; $local < $effectif; $local++) {
                $periodeDate = today()->startOfMonth()->subMonthsNoOverflow($local);
                $this->periodesPaieParCompagnie[$compagnieId][$local] = $this->ajouter('periodes_paie', [
                    'compagnie_id' => $compagnieId,
                    'annee' => $periodeDate->year,
                    'mois' => $periodeDate->month,
                    'statut' => 'brouillon',
                    'genere_le' => now()->toDateTimeString(),
                ]);
            }
        }

        for ($i = 0; $i < self::COUNT; $i++) {
            $compagnieId = $this->compagnie($i);
            $gares = $this->garesParCompagnie[$compagnieId];
            $employes[$i] = $this->ajouter('employes', [
                'compagnie_id' => $compagnieId,
                'gare_id' => $gares[$this->indexCompagnie($i) % count($gares)],
                'matricule' => sprintf('DEMO-MOD-RH-%05d', $i + 1),
                'nom' => sprintf('Employe%03d', $i + 1),
                'prenoms' => 'Démonstration',
                'sexe' => $i % 2 === 0 ? 'M' : 'F',
                'date_naissance' => today()->subYears(25 + ($i % 20))->toDateString(),
                'lieu_naissance' => 'Ville de démonstration',
                'nationalite' => 'Burkinabè',
                'situation_familiale' => 'celibataire',
                'telephone' => sprintf('+226760%06d', $i + 1),
                'email' => sprintf('employe%03d@example.test', $i + 1),
                'adresse' => 'Adresse fictive de démonstration.',
                'poste' => ['Agent de guichet', 'Chauffeur', 'Comptable', 'Agent logistique'][$i % 4],
                'service' => ['exploitation', 'administration', 'finance', 'transport'][$i % 4],
                'statut' => 'actif',
                'date_embauche' => today()->subYears(10)->toDateString(),
                'mode_paiement' => 'virement',
                'numero_paiement' => sprintf('DEMO-RIB-%05d', $i + 1),
                'cnss_numero' => sprintf('CNSS-DEMO-%05d', $i + 1),
                'observations' => 'Employé fictif de démonstration.',
                'cree_par' => $acteurId,
            ]);

            $salaire = 200000 + ($i * 1000);
            $contrats[$i] = $this->ajouter('contrats_employes', [
                'employe_id' => $employes[$i],
                'type' => 'cdi',
                'date_debut' => today()->subYears(10)->toDateString(),
                'categorie' => 'Démo',
                'mode' => 'mensuel',
                'salaire_base' => $salaire,
                'heures_hebdo' => 40,
                'elements_fixes' => json_encode([
                    ['libelle' => 'Indemnité de transport', 'montant' => 15000, 'soumis_cnss' => true, 'soumis_iuts' => true],
                ], JSON_THROW_ON_ERROR),
                'actif' => true,
                'cree_par' => $acteurId,
            ]);

            $periodeDate = today()->startOfMonth()->subMonthsNoOverflow($localIndex[$i]);
            $gareId = $gares[$this->indexCompagnie($i) % count($gares)];

            $this->ajouter('presences_employes', [
                'compagnie_id' => $compagnieId,
                'gare_id' => $gareId,
                'employe_id' => $employes[$i],
                'date' => $periodeDate->toDateString(),
                'statut' => 'present',
                'heure_arrivee' => '08:00:00',
                'heure_depart' => '17:00:00',
                'minutes_retard' => 0,
                'heures_sup_minutes' => 0,
                'note' => 'Présence fictive.',
                'cree_par' => $acteurId,
            ]);

            $this->ajouter('presences_employes', [
                'compagnie_id' => $compagnieId,
                'gare_id' => $gareId,
                'employe_id' => $employes[$i],
                'date' => $periodeDate->copy()->addDay()->toDateString(),
                'statut' => 'absent_injustifie',
                'note' => 'Absence fictive destinée à illustrer la retenue de paie.',
                'cree_par' => $acteurId,
            ]);

            $this->ajouter('conges_employes', [
                'compagnie_id' => $compagnieId,
                'employe_id' => $employes[$i],
                'type' => 'annuel',
                'date_debut' => today()->addDays($i + 1)->toDateString(),
                'date_fin' => today()->addDays($i + 4)->toDateString(),
                'jours' => 4,
                'statut' => $i % 3 === 0 ? 'approuve' : 'demande',
                'motif' => 'Demande fictive de démonstration.',
                'decide_par' => $i % 3 === 0 ? $acteurId : null,
                'decide_le' => $i % 3 === 0 ? now()->toDateTimeString() : null,
                'cree_par' => $acteurId,
            ]);

            $avances[$i] = $this->ajouter('avances_employes', [
                'compagnie_id' => $compagnieId,
                'employe_id' => $employes[$i],
                'type' => $i % 4 === 0 ? 'pret' : 'avance',
                'montant' => 50000 + ($i * 1000),
                'nb_mensualites' => 5,
                'mensualite' => 10000,
                'reste_du' => 40000,
                'date_octroi' => $periodeDate->copy()->subDays(5)->toDateString(),
                'debut_remboursement' => $periodeDate->toDateString(),
                'motif' => 'Avance fictive de démonstration.',
                'statut' => 'en_cours',
                'cree_par' => $acteurId,
            ]);

            $periodeId = $this->periodesPaieParCompagnie[$compagnieId][$localIndex[$i]];
            $periode = PeriodePaie::query()->findOrFail($periodeId);
            $employe = Employe::query()->findOrFail($employes[$i]);
            $contrat = ContratEmploye::query()->findOrFail($contrats[$i]);
            $calcul = CalculPaie::calculer($employe, $contrat, $periode);
            $detail = $calcul['detail'];
            $calcul['detail'] = json_encode($detail, JSON_THROW_ON_ERROR);
            $bulletinId = $this->ajouter('bulletins_paie', $calcul + [
                'periode_paie_id' => $periodeId,
                'compagnie_id' => $compagnieId,
                'employe_id' => $employes[$i],
                'contrat_id' => $contrats[$i],
                'statut_paiement' => 'a_payer',
                'mode_paiement' => 'virement',
                'reference_paiement' => sprintf('DEMO-MOD-SAL-%05d', $i + 1),
                'observation' => 'Bulletin calculé par le moteur de paie pour la démonstration.',
            ]);

            foreach ($detail['avances'] as $remboursement) {
                $this->ajouter('remboursements_avances', [
                    'avance_id' => $remboursement['avance_id'],
                    'bulletin_id' => $bulletinId,
                    'montant' => $remboursement['montant'],
                ]);

                $resteDu = max(0, (int) DB::table('avances_employes')->where('id', $remboursement['avance_id'])->value('reste_du') - $remboursement['montant']);
                DB::table('avances_employes')->where('id', $remboursement['avance_id'])->update([
                    'reste_du' => $resteDu,
                    'statut' => $resteDu === 0 ? 'solde' : 'en_cours',
                ]);
            }

            DB::table('periodes_paie')->where('id', $periodeId)->update([
                'statut' => 'validee',
                'valide_par' => $acteurId,
                'valide_le' => now()->toDateTimeString(),
            ]);

            $this->ajouter('sanctions_employes', [
                'compagnie_id' => $compagnieId,
                'employe_id' => $employes[$i],
                'type' => 'avertissement',
                'date_faits' => today()->subDays($i + 5)->toDateString(),
                'date_sanction' => today()->subDays($i)->toDateString(),
                'jours_mise_a_pied' => 0,
                'motif' => 'Exemple fictif sans incidence disciplinaire réelle.',
                'notes' => 'Donnée créée uniquement pour la démonstration.',
                'cree_par' => $acteurId,
            ]);

            $this->ajouter('documents_employes', [
                'compagnie_id' => $compagnieId,
                'employe_id' => $employes[$i],
                'type' => 'contrat',
                'libelle' => 'Document de démonstration',
                'fichier' => sprintf('demo/modules/document-%03d.pdf', $i + 1),
                'expire_le' => today()->addYear()->toDateString(),
                'cree_par' => $acteurId,
            ]);
        }

    }

    private function creerComptabilite(int $acteurId): void
    {
        $exercicesCourants = [];

        foreach ($this->compagnieIds as $compagnieId) {
            $anneeCourante = (int) today()->year;
            $exercicesCourants[$compagnieId] = DB::table('cpt_exercices')
                ->where('compagnie_id', $compagnieId)
                ->whereDate('date_debut', '<=', today()->toDateString())
                ->whereDate('date_fin', '>=', today()->toDateString())
                ->value('id');

            if (! $exercicesCourants[$compagnieId]) {
                $exercicesCourants[$compagnieId] = $this->ajouter('cpt_exercices', [
                    'compagnie_id' => $compagnieId,
                    'libelle' => 'Exercice '.$anneeCourante,
                    'date_debut' => $anneeCourante.'-01-01',
                    'date_fin' => $anneeCourante.'-12-31',
                    'cloture' => false,
                ]);
            }

            for ($historique = 0; $historique < self::COUNT; $historique++) {
                $annee = $anneeCourante - self::COUNT + $historique;
                $debut = sprintf('%04d-01-01', $annee);
                DB::table('cpt_exercices')->insertOrIgnore([
                    'compagnie_id' => $compagnieId,
                    'libelle' => 'Exercice '.$annee.' · démonstration',
                    'date_debut' => $debut,
                    'date_fin' => sprintf('%04d-12-31', $annee),
                    'cloture' => true,
                    'created_at' => now()->toDateTimeString(),
                    'updated_at' => now()->toDateTimeString(),
                ]);
            }
        }

        for ($i = 0; $i < self::COUNT; $i++) {
            $compagnieId = $this->compagnie($i);
            $this->comptesParCompagnie[$compagnieId][] = $this->ajouter('cpt_comptes', [
                'compagnie_id' => $compagnieId,
                'numero' => sprintf('57%08d', $i + 1),
                'intitule' => sprintf('Compte de trésorerie fictif %03d', $i + 1),
                'classe' => 5,
                'actif' => true,
            ]);
            $this->comptesParCompagnie[$compagnieId][] = $this->ajouter('cpt_comptes', [
                'compagnie_id' => $compagnieId,
                'numero' => sprintf('701%07d', $i + 1),
                'intitule' => sprintf('Compte de recettes fictif %03d', $i + 1),
                'classe' => 7,
                'actif' => true,
            ]);

            $this->ajouter('cpt_journaux', [
                'compagnie_id' => $compagnieId,
                'code' => sprintf('D%04d', $i + 1),
                'libelle' => sprintf('Journal de démonstration %03d', $i + 1),
                'type' => 'od',
                'dernier_numero' => 0,
            ]);

            $this->sectionsParCompagnie[$compagnieId][] = $this->ajouter('cpt_sections', [
                'compagnie_id' => $compagnieId,
                'code' => sprintf('DEMO%04d', $i + 1),
                'libelle' => sprintf('Section fictive %03d', $i + 1),
                'type' => 'activite',
                'actif' => true,
            ]);
        }

        $journaux = [];

        for ($i = 0; $i < self::COUNT; $i++) {
            $compagnieId = $this->compagnie($i);
            $this->journauxParCompagnie[$compagnieId][] = $this->ajouter('cpt_journaux', [
                'compagnie_id' => $compagnieId,
                'code' => sprintf('X%04d', $i + 1),
                'libelle' => sprintf('Journal auxiliaire fictif %03d', $i + 1),
                'type' => 'od',
                'dernier_numero' => 0,
            ]);
        }

        $actifComptes = [];
        $amortComptes = [];
        $chargeComptes = [];

        foreach ($this->compagnieIds as $index => $compagnieId) {
            $actifComptes[$compagnieId] = $this->ajouter('cpt_comptes', [
                'compagnie_id' => $compagnieId,
                'numero' => sprintf('245%07d', $index + 1),
                'intitule' => 'Immobilisations de démonstration',
                'classe' => 2,
                'actif' => true,
            ]);
            $amortComptes[$compagnieId] = $this->ajouter('cpt_comptes', [
                'compagnie_id' => $compagnieId,
                'numero' => sprintf('284%07d', $index + 1),
                'intitule' => 'Amortissements de démonstration',
                'classe' => 2,
                'actif' => true,
            ]);
            $chargeComptes[$compagnieId] = $this->ajouter('cpt_comptes', [
                'compagnie_id' => $compagnieId,
                'numero' => sprintf('681%07d', $index + 1),
                'intitule' => 'Dotations de démonstration',
                'classe' => 6,
                'actif' => true,
            ]);
        }

        for ($i = 0; $i < self::COUNT; $i++) {
            $compagnieId = $this->compagnie($i);
            $this->categoriesImmoParCompagnie[$compagnieId][] = $this->ajouter('cpt_categories_immobilisations', [
                'compagnie_id' => $compagnieId,
                'code' => sprintf('DM%04d', $i + 1),
                'libelle' => sprintf('Catégorie fictive %03d', $i + 1),
                'duree_utilite_mois' => 120,
                'mode_amortissement' => 'lineaire',
                'premiere_periode' => 'prorata_temporis',
                'compte_actif_id' => $actifComptes[$compagnieId],
                'compte_amortissement_id' => $amortComptes[$compagnieId],
                'compte_charge_id' => $chargeComptes[$compagnieId],
                'actif' => true,
                'cree_par' => $acteurId,
            ]);
        }

        for ($i = 0; $i < self::COUNT; $i++) {
            $compagnieId = $this->compagnie($i);
            $categories = $this->categoriesImmoParCompagnie[$compagnieId];
            $this->immobilisationIds[$i] = $this->ajouter('cpt_immobilisations', [
                'compagnie_id' => $compagnieId,
                'categorie_id' => $categories[$this->indexCompagnie($i) % count($categories)],
                'code' => sprintf('DEMO-MOD-IMMO-%05d', $i + 1),
                'designation' => sprintf('Immobilisation fictive %03d', $i + 1),
                'description' => 'Bien fictif destiné à l’aperçu du module.',
                'date_acquisition' => today()->subYears(1)->toDateString(),
                'date_mise_en_service' => today()->subYears(1)->toDateString(),
                'cout_acquisition' => 5000000 + ($i * 100000),
                'valeur_residuelle' => 250000,
                'duree_utilite_mois' => 120,
                'mode_amortissement' => 'lineaire',
                'premiere_periode' => 'prorata_temporis',
                'compte_actif_id' => $actifComptes[$compagnieId],
                'compte_amortissement_id' => $amortComptes[$compagnieId],
                'compte_charge_id' => $chargeComptes[$compagnieId],
                'statut' => 'active',
                'cree_par' => $acteurId,
                'valide_par' => $acteurId,
                'validee_le' => now()->toDateTimeString(),
            ]);

            $this->ajouter('cpt_amortissements', [
                'compagnie_id' => $compagnieId,
                'immobilisation_id' => $this->immobilisationIds[$i],
                'periode_debut' => today()->startOfMonth()->subMonthNoOverflow()->toDateString(),
                'periode_fin' => today()->startOfMonth()->subDay()->toDateString(),
                'montant' => (int) round((5000000 + ($i * 100000) - 250000) / 120),
                'statut' => 'planifie',
                'cree_par' => $acteurId,
            ]);
        }

        for ($i = 0; $i < self::COUNT; $i++) {
            $compagnieId = $this->compagnie($i);
            $routes = $this->routesParCompagnie[$compagnieId];
            $comptes = $this->comptesParCompagnie[$compagnieId];
            $sections = $this->sectionsParCompagnie[$compagnieId];
            $ecritureId = $this->ajouter('cpt_ecritures', [
                'compagnie_id' => $compagnieId,
                'exercice_id' => $exercicesCourants[$compagnieId],
                'journal_id' => $this->journauxParCompagnie[$compagnieId][$this->indexCompagnie($i) % count($this->journauxParCompagnie[$compagnieId])],
                'numero' => sprintf('DEMO-MOD-ECR-%05d', $i + 1),
                'date_ecriture' => today()->toDateString(),
                'libelle' => sprintf('Écriture équilibrée fictive %03d', $i + 1),
                'reference' => sprintf('DEMO-MOD-CPT-%05d', $i + 1),
                'gare_id' => $this->garesParCompagnie[$compagnieId][$this->indexCompagnie($i) % count($this->garesParCompagnie[$compagnieId])],
                'pieces' => json_encode([], JSON_THROW_ON_ERROR),
                'statut' => 'comptabilise',
                'cree_par' => $acteurId,
                'comptabilise_le' => now()->toDateTimeString(),
            ]);
            $montant = 10000 + ($i * 500);

            $this->ajouter('cpt_lignes', [
                'ecriture_id' => $ecritureId,
                'compte_id' => $comptes[($i * 2) % count($comptes)],
                'libelle' => 'Débit fictif',
                'debit' => $montant,
                'credit' => 0,
                'section_id' => $sections[$this->indexCompagnie($i) % count($sections)],
            ]);
            $this->ajouter('cpt_lignes', [
                'ecriture_id' => $ecritureId,
                'compte_id' => $comptes[(($i * 2) + 1) % count($comptes)],
                'libelle' => 'Crédit fictif',
                'debit' => 0,
                'credit' => $montant,
                'section_id' => $sections[$this->indexCompagnie($i) % count($sections)],
            ]);
        }
    }

    private function compagnie(int $index): int
    {
        return $this->compagnieIds[$index % count($this->compagnieIds)];
    }

    private function indexCompagnie(int $index): int
    {
        return intdiv($index, count($this->compagnieIds));
    }

    /**
     * @param  array<string, mixed>  $attributs
     */
    private function ajouter(string $table, array $attributs): int
    {
        $now = now()->toDateTimeString();
        $colonnes = $this->colonnesParTable[$table] ??= Schema::getColumnListing($table);

        if (in_array('created_at', $colonnes, true)) {
            $attributs['created_at'] ??= $now;
        }

        if (in_array('updated_at', $colonnes, true)) {
            $attributs['updated_at'] ??= $now;
        }

        return (int) DB::table($table)->insertGetId($attributs);
    }
}
