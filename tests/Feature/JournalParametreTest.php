<?php

namespace Tests\Feature;

use App\Models\CgParametre;
use App\Models\ChargeStructure;
use App\Models\Compagnie;
use App\Models\FraisRegle;
use App\Models\Gare;
use App\Models\JournalParametre as EntreeJournal;
use App\Models\RouteTransport;
use App\Models\User;
use App\Models\Ville;
use App\Services\Modules;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class JournalParametreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_les_modifications_conservent_auteur_ancienne_et_nouvelle_valeur(): void
    {
        $compagnie = Compagnie::factory()->create([
            'nom' => 'Compagnie journal',
            'places_par_depart' => 60,
        ]);
        $administrateur = $this->creerAdministrateur($compagnie);
        $this->actingAs($administrateur);
        $this->freezeTime();
        $instant = now();

        $compagnie->update(['places_par_depart' => 55]);

        $entree = EntreeJournal::query()
            ->where('entite', 'compagnies')
            ->where('entite_id', $compagnie->id)
            ->where('evenement', 'modification')
            ->firstOrFail();

        $this->assertSame($administrateur->id, $entree->acteur_id);
        $this->assertSame('Admin Audit', $entree->acteur_nom);
        $this->assertSame('Compagnie journal', $entree->compagnie_nom);
        $this->assertSame(60, (int) $entree->valeurs['places_par_depart']['ancienne']);
        $this->assertSame(55, (int) $entree->valeurs['places_par_depart']['nouvelle']);
        $this->assertSame($instant->timestamp, $entree->enregistre_le->timestamp);
    }

    public function test_une_modification_sans_parametre_ne_cree_pas_dentree(): void
    {
        $compagnie = Compagnie::factory()->create(['nom' => 'Avant']);
        $administrateur = $this->creerAdministrateur($compagnie);
        $nombreEntrees = EntreeJournal::query()->count();
        $this->actingAs($administrateur);

        $compagnie->update(['nom' => 'Après']);

        $this->assertSame($nombreEntrees, EntreeJournal::query()->count());
    }

    public function test_les_seuils_tarifs_charges_quotas_et_prix_de_route_sont_journalises(): void
    {
        $compagnie = Compagnie::factory()->create();
        $administrateur = $this->creerAdministrateur($compagnie);
        $this->actingAs($administrateur);

        $parametres = CgParametre::create([
            'compagnie_id' => $compagnie->id,
            'norme_l100' => 30,
            'prix_litre' => 700,
            'ecart_seuil_pct' => 10,
            'commission_paiement_pct' => 2,
        ]);
        $parametres->update(['ecart_seuil_pct' => 15]);

        $tarif = FraisRegle::create([
            'compagnie_id' => $compagnie->id,
            'mode' => 'fixe',
            'valeur' => 500,
            'actif' => true,
        ]);
        $tarif->update(['valeur' => 650]);

        $charge = ChargeStructure::create([
            'compagnie_id' => $compagnie->id,
            'niveau' => 'siege',
            'libelle' => 'Loyer',
            'montant' => 100000,
            'periode_debut' => '2026-11-01',
            'periode_fin' => '2026-11-30',
            'cle' => 'departs',
        ]);
        $charge->update(['cle' => 'voyageurs']);

        $ville = Ville::create(['nom' => 'Ville journal']);
        $gare = Gare::create([
            'compagnie_id' => $compagnie->id,
            'ville_id' => $ville->id,
            'quartier' => 'Gare journal',
        ]);
        $gare->forceFill(['quota_app_pourcent' => 40])->save();

        $route = RouteTransport::create([
            'compagnie_id' => $compagnie->id,
            'gare_depart_id' => $gare->id,
            'ville_arrivee_id' => $ville->id,
            'prix' => 5000,
            'actif' => true,
        ]);
        $route->update(['prix' => 5500]);

        $seuil = EntreeJournal::query()
            ->where('entite', 'cg_parametres')
            ->where('entite_id', $parametres->id)
            ->where('evenement', 'modification')
            ->firstOrFail();
        $this->assertSame(15, (int) $seuil->valeurs['ecart_seuil_pct']['nouvelle']);

        $nouveauTarif = EntreeJournal::query()
            ->where('entite', 'frais_regles')
            ->where('entite_id', $tarif->id)
            ->where('evenement', 'modification')
            ->firstOrFail();
        $this->assertSame(650, (int) $nouveauTarif->valeurs['valeur']['nouvelle']);

        $nouvelleCle = EntreeJournal::query()
            ->where('entite', 'cg_charges_structure')
            ->where('entite_id', $charge->id)
            ->where('evenement', 'modification')
            ->firstOrFail();
        $this->assertSame('voyageurs', $nouvelleCle->valeurs['cle']['nouvelle']);

        $quota = EntreeJournal::query()
            ->where('entite', 'gares')
            ->where('entite_id', $gare->id)
            ->where('evenement', 'modification')
            ->firstOrFail();
        $this->assertSame(40, (int) $quota->valeurs['quota_app_pourcent']['nouvelle']);

        $nouveauPrix = EntreeJournal::query()
            ->where('entite', 'route_transports')
            ->where('entite_id', $route->id)
            ->where('evenement', 'modification')
            ->firstOrFail();
        $this->assertSame(5500, (int) $nouveauPrix->valeurs['prix']['nouvelle']);
    }

    public function test_les_attributions_de_role_sont_journalisees_avec_lutilisateur_cible(): void
    {
        $compagnie = Compagnie::factory()->create();
        $administrateur = $this->creerAdministrateur($compagnie);
        $ville = Ville::create(['nom' => 'Ville rôles']);
        $gare = Gare::create([
            'compagnie_id' => $compagnie->id,
            'ville_id' => $ville->id,
            'quartier' => 'Gare rôles',
        ]);
        $utilisateur = User::create([
            'prenom' => 'Agent',
            'nom' => 'Audit',
            'telephone' => '70000002',
            'telephone_verifie' => true,
            'actif' => true,
            'password' => 'motdepasse123',
        ]);
        $this->actingAs($administrateur);

        $utilisateur->assignerRolePourCompagnie('agent_gare', $compagnie->id, $gare->id);
        $utilisateur->update(['actif' => false]);
        $liaison = $utilisateur->compagnieRoles()->firstOrFail();
        $liaison->delete();

        $entrees = EntreeJournal::query()
            ->where('entite', 'user_compagnie_roles')
            ->where('cible_utilisateur_id', $utilisateur->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $entrees);
        $this->assertSame('Agent Audit', $entrees[0]->cible_utilisateur_nom);
        $this->assertSame('creation', $entrees[0]->evenement);
        $this->assertSame('suppression', $entrees[1]->evenement);
        $this->assertSame('agent_gare', $entrees[0]->valeurs['role_id']['nouvelle']['nom']);
        $compteDesactive = EntreeJournal::query()
            ->where('entite', 'users')
            ->where('entite_id', $utilisateur->id)
            ->where('evenement', 'modification')
            ->firstOrFail();
        $this->assertSame($administrateur->id, $compteDesactive->acteur_id);
        $this->assertTrue($compteDesactive->valeurs['actif']['ancienne']);
        $this->assertFalse($compteDesactive->valeurs['actif']['nouvelle']);
        $this->assertDatabaseHas('ci_journal_parametres', [
            'entite' => 'users',
            'entite_id' => $utilisateur->id,
            'compagnie_id' => $compagnie->id,
            'evenement' => 'modification',
        ]);
    }

    public function test_les_modules_sont_journalises_uniquement_si_leur_etat_change(): void
    {
        $compagnie = Compagnie::factory()->create();
        $administrateur = $this->creerAdministrateur($compagnie);
        $this->actingAs($administrateur);

        Modules::definirCompagnie($compagnie->id, 'audit_interne', true);
        Modules::definirCompagnie($compagnie->id, 'audit_interne', true);
        Modules::definirCompagnie($compagnie->id, 'audit_interne', false);

        $ville = Ville::create(['nom' => 'Ville modules']);
        $gare = Gare::create([
            'compagnie_id' => $compagnie->id,
            'ville_id' => $ville->id,
            'quartier' => 'Gare modules',
        ]);
        Modules::definirGare($gare->id, 'audit_interne', true);

        $entrees = EntreeJournal::query()
            ->where('entite', 'modules_compagnies')
            ->where('compagnie_id', $compagnie->id)
            ->get();

        $this->assertCount(2, $entrees);
        $this->assertSame('creation', $entrees[0]->evenement);
        $this->assertSame('modification', $entrees[1]->evenement);
        $this->assertTrue($entrees[0]->valeurs['actif']['nouvelle']);
        $this->assertFalse($entrees[1]->valeurs['actif']['nouvelle']);
        $this->assertDatabaseHas('ci_journal_parametres', [
            'entite' => 'modules_gares',
            'compagnie_id' => $compagnie->id,
            'entite_id' => DB::table('modules_gares')->where('gare_id', $gare->id)->value('id'),
        ]);
    }

    public function test_le_modele_refuse_la_modification_du_journal(): void
    {
        $compagnie = Compagnie::factory()->create(['places_par_depart' => 60]);
        $this->creerAdministrateur($compagnie);
        $entree = EntreeJournal::query()->where('entite', 'compagnies')->firstOrFail();

        $this->expectException(LogicException::class);

        $entree->update(['acteur_nom' => 'Altéré']);
    }

    public function test_la_base_refuse_la_modification_et_la_suppression_directes_du_journal(): void
    {
        $compagnie = Compagnie::factory()->create(['places_par_depart' => 60]);
        $this->creerAdministrateur($compagnie);
        $entree = EntreeJournal::query()->where('entite', 'compagnies')->firstOrFail();

        try {
            DB::table('ci_journal_parametres')->where('id', $entree->id)->update(['acteur_nom' => 'Altéré']);
            $this->fail('Une mise à jour SQL directe ne doit pas modifier le journal.');
        } catch (QueryException) {
        }

        try {
            DB::table('ci_journal_parametres')->where('id', $entree->id)->delete();
            $this->fail('Une suppression SQL directe ne doit pas supprimer le journal.');
        } catch (QueryException) {
        }

        $this->assertModelExists($entree);
    }

    public function test_un_administrateur_ne_voit_que_le_journal_de_sa_compagnie(): void
    {
        $compagnieA = Compagnie::factory()->create(['nom' => 'Compagnie journal A']);
        $compagnieB = Compagnie::factory()->create([
            'nom' => 'Compagnie journal B',
            'places_par_depart' => 60,
        ]);
        $compagnieB->update(['places_par_depart' => 50]);
        FraisRegle::create([
            'mode' => 'fixe',
            'valeur' => 500,
            'actif' => true,
        ]);
        $administrateur = $this->creerAdministrateur($compagnieA);
        Modules::definirCompagnie($compagnieA->id, 'audit_interne', true);

        $this->actingAs($administrateur)
            ->get('/admin/journal-parametres')
            ->assertOk()
            ->assertSee('Compagnie journal A')
            ->assertSee('Tarif général · toutes les compagnies')
            ->assertDontSee('Compagnie journal B');
    }

    private function creerAdministrateur(Compagnie $compagnie): User
    {
        $administrateur = User::create([
            'prenom' => 'Admin',
            'nom' => 'Audit',
            'telephone' => '70000001',
            'telephone_verifie' => true,
            'actif' => true,
            'password' => 'motdepasse123',
        ]);
        $administrateur->assignerRolePourCompagnie('admin_compagnie', $compagnie->id);

        return $administrateur;
    }
}
