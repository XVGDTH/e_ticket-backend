<?php

namespace Tests\Feature;

use App\Models\Compagnie;
use App\Models\User;
use App\Services\ControleGestion\Reporting;
use App\Services\Modules;
use Database\Seeders\DemoModulesSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DemoModulesSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Modules::oublier();
        config(['securite.mfa_obligatoire_admins' => false]);
    }

    public function test_cree_cent_lignes_par_table_metier_et_ne_les_duplique_pas(): void
    {
        $compagnies = Compagnie::factory()->count(2)->create();
        $administrateur = User::create([
            'prenom' => 'Admin',
            'nom' => 'Démo',
            'telephone' => '70000005',
            'telephone_verifie' => true,
            'actif' => true,
            'password' => 'motdepasse123',
        ]);
        $administrateur->assignerRolePourCompagnie('super_admin');

        (new DemoModulesSeeder)->run();
        (new DemoModulesSeeder)->run();

        foreach ([
            'villes', 'gares', 'route_transports', 'horaires', 'vehicules', 'chauffeurs',
            'voyages_cars', 'depenses_vehicules', 'billets', 'billet_evenements',
            'verifications_billets', 'caisses', 'mouvements_caisse', 'frais_regles',
            'colis', 'colis_evenements', 'ci_controles', 'ci_recommandations',
            'ci_journal_parametres', 'ci_sequences_documents', 'ci_fournisseurs',
            'ci_articles_stock', 'ci_receptions_fournisseurs', 'ci_lignes_reception_fournisseur',
            'ci_mouvements_stock', 'ci_factures_fournisseurs', 'ci_paiements_fournisseurs',
            'cg_budgets', 'cg_budget_lignes', 'cg_commentaires', 'cg_clotures',
            'cg_charges_structure', 'employes', 'contrats_employes', 'presences_employes',
            'conges_employes', 'avances_employes', 'sanctions_employes', 'documents_employes',
            'periodes_paie', 'bulletins_paie', 'remboursements_avances', 'cpt_comptes',
            'cpt_journaux', 'cpt_sections', 'cpt_categories_immobilisations',
            'cpt_immobilisations', 'cpt_amortissements', 'cpt_ecritures', 'cpt_exercices',
        ] as $table) {
            $this->assertGreaterThanOrEqual(100, DB::table($table)->count(), $table);
        }

        $this->assertDatabaseCount('cg_parametres', 2);
        $this->assertDatabaseCount('cpt_lignes', 200);
        $this->assertSame(
            2,
            DB::table('route_transports as routes')
                ->join('gares', 'gares.id', '=', 'routes.gare_depart_id')
                ->whereColumn('gares.compagnie_id', 'routes.compagnie_id')
                ->distinct()
                ->count('routes.compagnie_id')
        );
        $this->assertSame(
            100,
            DB::table('cpt_lignes as lignes')
                ->join('cpt_ecritures as ecritures', 'ecritures.id', '=', 'lignes.ecriture_id')
                ->where('ecritures.reference', 'like', 'DEMO-MOD-CPT-%')
                ->select('ecritures.id')
                ->groupBy('ecritures.id')
                ->havingRaw('SUM(lignes.debit) = SUM(lignes.credit)')
                ->get()
                ->count()
        );
        $this->assertDatabaseCount('ci_controles', 100);
        $this->assertDatabaseCount('billets', 100);
        $this->assertDatabaseCount('colis', 100);
        $this->assertSame(
            0,
            DB::table('gares as gares')
                ->leftJoin('voyages_cars as voyages', 'voyages.gare_id', '=', 'gares.id')
                ->where('gares.quartier', 'like', 'DEMO-MOD-GARE-%')
                ->whereNull('voyages.id')
                ->count()
        );
        $this->assertDatabaseCount('cg_budget_lignes', 1200);
        $this->assertDatabaseCount('cpt_immobilisations', 100);
        $this->assertSame(100, DB::table('cpt_immobilisations')->where('statut', 'active')->count());
        $bulletin = DB::table('bulletins_paie')->where('reference_paiement', 'DEMO-MOD-SAL-00001')->first();
        $this->assertGreaterThan(0, $bulletin->retenue_absences);
        $this->assertGreaterThan(0, $bulletin->cnss_salarie);
        $this->assertGreaterThan(0, $bulletin->iuts);
        $this->assertSame(
            (int) $bulletin->brut - (int) $bulletin->cnss_salarie - (int) $bulletin->iuts
                - (int) $bulletin->retenue_avance - (int) $bulletin->autres_retenues,
            (int) $bulletin->net_a_payer
        );
        $this->assertSame(
            100,
            DB::table('cpt_lignes')
                ->join('cpt_ecritures', 'cpt_ecritures.id', '=', 'cpt_lignes.ecriture_id')
                ->join('cpt_comptes', 'cpt_comptes.id', '=', 'cpt_lignes.compte_id')
                ->where('cpt_ecritures.reference', 'like', 'DEMO-MOD-CPT-%')
                ->where('cpt_comptes.numero', 'like', '701%')
                ->count()
        );
        $this->assertCount(2, $compagnies);

        $cloture = DB::table('cg_clotures')->orderBy('id')->first();
        $snapshot = json_decode($cloture->snapshot, true, flags: JSON_THROW_ON_ERROR);
        $rapport = Reporting::mois($cloture->compagnie_id, $cloture->annee, $cloture->mois);
        $this->assertSame(
            $rapport['reel']['total'],
            $snapshot['reel']['total']
        );

        $this->actingAs($administrateur)
            ->get('/admin/maintenance-et-carburant')
            ->assertOk()
            ->assertSee('Toutes les compagnies')
            ->assertSee('Dans le périmètre sélectionné')
            ->assertSee('100');

        $this->get('/admin/rentabilite-flotte')
            ->assertOk()
            ->assertSee('Toutes les compagnies')
            ->assertSee('DEMO-MOD-00001')
            ->assertSee('Classement des cars');

        $this->get('/admin/controle-interne')
            ->assertOk()
            ->assertSee('Toutes les compagnies')
            ->assertSee('DEMO-MOD-100')
            ->assertSee((string) $compagnies[0]->nom)
            ->assertDontSee('Planifier un contrôle');

        $this->get('/admin/controle-de-gestion')
            ->assertOk()
            ->assertSee('Toutes les compagnies')
            ->assertSee('Vue globale en lecture seule')
            ->assertSee((string) $compagnies[0]->nom);

        $this->get('/admin/charges-de-structure')
            ->assertOk()
            ->assertSee('Toutes les compagnies')
            ->assertSee('Charge fictive');

        $this->get('/admin/budget')
            ->assertOk()
            ->assertSee('Toutes les compagnies')
            ->assertSee('Voir pour cette compagnie');

        $this->get('/admin/ecarts')
            ->assertOk()
            ->assertSee('Écarts par compagnie');

        $this->get('/admin/reporting-mensuel')
            ->assertOk()
            ->assertSee('Reporting par compagnie');

        $this->get('/admin/cycle-fournisseurs')
            ->assertOk()
            ->assertSee('Toutes les compagnies')
            ->assertSee('Vue globale des fournisseurs et des stocks')
            ->assertSee('50 fournisseur(s)')
            ->assertSee('Articles en stock');

        $administrateurCompagnie = User::create([
            'prenom' => 'Admin',
            'nom' => 'Compagnie',
            'telephone' => '70000006',
            'telephone_verifie' => true,
            'actif' => true,
            'password' => 'motdepasse123',
        ]);
        Modules::definirCompagnie($compagnies[0]->id, 'audit_interne', true);
        Modules::definirCompagnie($compagnies[0]->id, 'controle_gestion', true);
        $administrateurCompagnie->assignerRolePourCompagnie('admin_compagnie', $compagnies[0]->id);

        $this->flushSession()
            ->actingAs($administrateurCompagnie)
            ->get('/admin/cycle-fournisseurs')
            ->assertOk()
            ->assertDontSee('Toutes les compagnies')
            ->assertSee((string) $compagnies[0]->nom)
            ->assertDontSee((string) $compagnies[1]->nom);

        $this->get('/admin/charges-de-structure')
            ->assertOk()
            ->assertDontSee('Toutes les compagnies')
            ->assertDontSee((string) $compagnies[1]->nom);
    }

    public function test_ne_cree_pas_de_donnees_de_demonstration_en_production(): void
    {
        $this->app->detectEnvironment(static fn (): string => 'production');

        (new DemoModulesSeeder)->run();

        $this->assertDatabaseCount('ci_controles', 0);
        $this->assertDatabaseCount('vehicules', 0);
    }
}
