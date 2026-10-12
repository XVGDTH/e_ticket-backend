<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Models\AvisGare;
use App\Models\Billet;
use App\Models\Compagnie;
use App\Models\Gare;
use App\Models\RouteTransport;
use App\Models\User;
use App\Models\Ville;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;


class IsolationCompagniesTest extends TestCase
{
    use RefreshDatabase;

    private Compagnie $staf;
    private Compagnie $elitis;
    private Ville $ville;
    private Gare $gareLarle;
    private Gare $gareTampouy;
    private Gare $gareElitis;

    private User $superAdmin;
    private User $adminStaf;
    private User $adminElitis;
    private User $agentLarle;
    private User $agentElitis;
    private User $client;

    protected function setUp(): void
    {
        parent::setUp();
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));

        $this->staf = Compagnie::create(['nom' => 'StafTransport', 'slug' => 'staf', 'actif' => true]);
        $this->elitis = Compagnie::create(['nom' => 'ElitisTransport', 'slug' => 'elitis', 'actif' => true]);
        $this->ville = Ville::create(['nom' => 'Ouagadougou']);

        $this->gareLarle = $this->creerGare($this->staf, 'GareStafLarle');
        $this->gareTampouy = $this->creerGare($this->staf, 'GareStafTampouy');
        $this->gareElitis = $this->creerGare($this->elitis, 'GareElitisCentre');

        $this->client = $this->creerUtilisateur('ClientMobile', '70000000');

        $this->creerBilletEtAvis($this->gareLarle, 'BILSTAFLARLE', 'AvisStafLarle');
        $this->creerBilletEtAvis($this->gareTampouy, 'BILSTAFTAMPOUY', 'AvisStafTampouy');
        $this->creerBilletEtAvis($this->gareElitis, 'BILELITIS', 'AvisElitis');

        $this->superAdmin = $this->creerUtilisateur('SuperAdmin', '71000001');
        $this->superAdmin->assignerRolePourCompagnie('super_admin', null);

        $this->adminStaf = $this->creerUtilisateur('AdminStaf', '71000002');
        $this->adminStaf->assignerRolePourCompagnie('admin_compagnie', $this->staf->id);

        $this->adminElitis = $this->creerUtilisateur('AdminElitis', '71000003');
        $this->adminElitis->assignerRolePourCompagnie('admin_compagnie', $this->elitis->id);

        $this->agentLarle = $this->creerUtilisateur('AgentLarle', '71000004');
        $this->agentLarle->assignerRolePourCompagnie('agent_gare', $this->staf->id, $this->gareLarle->id);

        $this->agentElitis = $this->creerUtilisateur('AgentElitis', '71000005');
        $this->agentElitis->assignerRolePourCompagnie('agent_gare', $this->elitis->id, $this->gareElitis->id);
    }

    // ---------- Outils de préparation ----------

    private function creerUtilisateur(string $prenom, string $telephone): User
    {
        return User::create([
            'prenom' => $prenom,
            'nom' => 'Test',
            'telephone' => $telephone,
            'telephone_verifie' => true,
            'password' => 'motdepasse123',
        ]);
    }

    private function creerGare(Compagnie $compagnie, string $quartier): Gare
    {
        return Gare::create([
            'compagnie_id' => $compagnie->id,
            'ville_id' => $this->ville->id,
            'quartier' => $quartier,
        ]);
    }

    private function creerBilletEtAvis(Gare $gare, string $reference, string $commentaire): void
    {
        $route = RouteTransport::create([
            'compagnie_id' => $gare->compagnie_id,
            'gare_depart_id' => $gare->id,
            'ville_arrivee_id' => $this->ville->id,
            'prix' => 5000,
            'actif' => true,
        ]);

        $billet = Billet::create([
            'reference' => $reference,
            'user_id' => $this->client->id,
            'route_transport_id' => $route->id,
            'gare_id' => $gare->id,
            'date_voyage' => '2026-10-20',
            'heure' => '08:00',
            'travelers_count' => 1,
            'montant_transport' => 5000,
            'taxe' => 0,
            'operateur_paiement' => 'test',
            'voyageurs' => [['nom' => 'Voyageur', 'code' => '1']],
            'date_paiement' => now(),
        ]);

        AvisGare::create([
            'billet_id' => $billet->id,
            'gare_id' => $gare->id,
            'scores' => ['accueil' => 5],
            'commentaire' => $commentaire,
        ]);
    }

    private function tenterCreationUtilisateur(array $donnees): void
    {
        try {
            Livewire::test(CreateUser::class)->fillForm($donnees)->call('create');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    // ---------- Admin de compagnie (Staf) ----------

    public function test_admin_ne_voit_que_sa_compagnie(): void
    {
        $this->actingAs($this->adminStaf)
            ->get('/admin/compagnies')
            ->assertOk()
            ->assertSee('StafTransport')
            ->assertDontSee('ElitisTransport');
    }

    public function test_admin_ne_peut_pas_ouvrir_une_autre_compagnie(): void
    {
        $this->actingAs($this->adminStaf)
            ->get('/admin/compagnies/' . $this->elitis->id . '/edit')
            ->assertNotFound();
    }

    public function test_admin_ne_voit_que_les_gares_de_sa_compagnie(): void
    {
        $this->actingAs($this->adminStaf)
            ->get('/admin/gares')
            ->assertOk()
            ->assertSee('GareStafLarle')
            ->assertSee('GareStafTampouy')
            ->assertDontSee('GareElitisCentre');
    }

    public function test_admin_ne_peut_pas_ouvrir_une_gare_dune_autre_compagnie(): void
    {
        $this->actingAs($this->adminStaf)
            ->get('/admin/gares/' . $this->gareElitis->id . '/edit')
            ->assertNotFound();
    }

    public function test_admin_ne_voit_que_les_routes_de_sa_compagnie(): void
    {
        $this->actingAs($this->adminStaf)
            ->get('/admin/route-transports')
            ->assertOk()
            ->assertSee('GareStafLarle')
            ->assertDontSee('GareElitisCentre');
    }

    public function test_admin_ne_voit_que_les_billets_et_avis_de_sa_compagnie(): void
    {
        $this->actingAs($this->adminStaf)
            ->get('/admin/billets')
            ->assertOk()
            ->assertSee('BILSTAFLARLE')
            ->assertSee('BILSTAFTAMPOUY')
            ->assertDontSee('BILELITIS');

        $this->actingAs($this->adminStaf)
            ->get('/admin/avis-gares')
            ->assertOk()
            ->assertSee('AvisStafLarle')
            ->assertDontSee('AvisElitis');
    }

    public function test_admin_ne_voit_que_les_agents_de_sa_compagnie(): void
    {
        $this->actingAs($this->adminStaf)
            ->get('/admin/users')
            ->assertOk()
            ->assertSee('AgentLarle')
            ->assertDontSee('AgentElitis')
            ->assertDontSee('AdminElitis');
    }

    public function test_admin_ne_peut_pas_modifier_un_agent_dune_autre_compagnie(): void
    {
        $this->actingAs($this->adminStaf)
            ->get('/admin/users/' . $this->agentElitis->id . '/edit')
            ->assertNotFound();
    }

    public function test_admin_peut_ouvrir_la_modification_de_son_agent(): void
    {
        $this->actingAs($this->adminStaf)
            ->get('/admin/users/' . $this->agentLarle->id . '/edit')
            ->assertOk();
    }

    public function test_admin_na_pas_acces_aux_villes(): void
    {
        $this->actingAs($this->adminStaf)->get('/admin/villes')->assertForbidden();
    }

    public function test_admin_accede_au_tableau_de_bord(): void
    {
        $this->actingAs($this->adminStaf)->get('/admin')->assertOk();
    }

    // ---------- Imposition côté serveur ----------

    public function test_une_gare_creee_par_un_admin_est_rattachee_a_sa_compagnie(): void
    {
        $this->actingAs($this->adminStaf);

        $gare = Gare::create([
            'compagnie_id' => $this->elitis->id, // tentative de fraude
            'ville_id' => $this->ville->id,
            'quartier' => 'GareInfiltree',
        ]);

        $this->assertSame($this->staf->id, (int) $gare->compagnie_id);
    }

    public function test_un_admin_ne_peut_pas_deplacer_une_gare_vers_une_autre_compagnie(): void
    {
        $this->actingAs($this->adminStaf);

        $this->gareLarle->update(['compagnie_id' => $this->elitis->id]);

        $this->assertSame($this->staf->id, (int) $this->gareLarle->fresh()->compagnie_id);
    }

    public function test_un_admin_ne_peut_pas_creer_un_admin_de_compagnie(): void
    {
        $this->actingAs($this->adminStaf);

        $this->tenterCreationUtilisateur([
            'prenom' => 'Intrus',
            'nom' => 'Test',
            'telephone' => '72000001',
            'password' => 'motdepasse123',
            'role' => 'admin_compagnie',
        ]);

        $this->assertDatabaseMissing('users', ['telephone' => '72000001']);
    }

    public function test_un_admin_ne_peut_pas_creer_un_agent_dans_la_gare_dune_autre_compagnie(): void
    {
        $this->actingAs($this->adminStaf);

        $this->tenterCreationUtilisateur([
            'prenom' => 'Intrus',
            'nom' => 'Test',
            'telephone' => '72000002',
            'password' => 'motdepasse123',
            'role' => 'agent_gare',
            'gare_id' => $this->gareElitis->id,
        ]);

        $this->assertDatabaseMissing('users', ['telephone' => '72000002']);
    }

    public function test_un_admin_cree_un_agent_dans_sa_compagnie_et_sa_gare(): void
    {
        $this->actingAs($this->adminStaf);

        $this->tenterCreationUtilisateur([
            'prenom' => 'NouvelAgent',
            'nom' => 'Test',
            'telephone' => '72000003',
            'password' => 'motdepasse123',
            'role' => 'agent_gare',
            'gare_id' => $this->gareTampouy->id,
        ]);

        $agent = User::where('telephone', '72000003')->first();

        $this->assertNotNull($agent);
        $this->assertSame($this->staf->id, $agent->compagnieId());
        $this->assertSame($this->gareTampouy->id, $agent->gareId());
    }

    // ---------- Agent de gare (Larlé, compagnie Staf) ----------

    public function test_agent_ne_voit_que_sa_gare(): void
    {
        $this->actingAs($this->agentLarle)
            ->get('/admin/gares')
            ->assertOk()
            ->assertSee('GareStafLarle')
            ->assertDontSee('GareStafTampouy')
            ->assertDontSee('GareElitisCentre');
    }

    public function test_agent_ne_voit_que_les_billets_et_avis_de_sa_gare(): void
    {
        $this->actingAs($this->agentLarle)
            ->get('/admin/billets')
            ->assertOk()
            ->assertSee('BILSTAFLARLE')
            ->assertDontSee('BILSTAFTAMPOUY')
            ->assertDontSee('BILELITIS');

        $this->actingAs($this->agentLarle)
            ->get('/admin/avis-gares')
            ->assertOk()
            ->assertSee('AvisStafLarle')
            ->assertDontSee('AvisStafTampouy')
            ->assertDontSee('AvisElitis');
    }

    public function test_agent_ne_peut_rien_gerer_dautre(): void
    {
        $this->actingAs($this->agentLarle);

        $this->get('/admin/users')->assertForbidden();
        $this->get('/admin/users/create')->assertForbidden();
        $this->get('/admin/compagnies')->assertForbidden();
        $this->get('/admin/villes')->assertForbidden();
        $this->get('/admin/gares/create')->assertForbidden();
    }

    // ---------- Accès au panel ----------

    public function test_un_client_de_lappli_na_pas_acces_au_panel(): void
    {
        $this->actingAs($this->client)->get('/admin')->assertForbidden();
    }

    // ---------- Super admin ----------

    public function test_super_admin_voit_toutes_les_compagnies_et_gares(): void
    {
        $this->actingAs($this->superAdmin)
            ->get('/admin/compagnies')
            ->assertOk()
            ->assertSee('StafTransport')
            ->assertSee('ElitisTransport');

        $this->actingAs($this->superAdmin)
            ->get('/admin/gares')
            ->assertOk()
            ->assertSee('GareStafLarle')
            ->assertSee('GareElitisCentre');

        $this->actingAs($this->superAdmin)->get('/admin/villes')->assertOk();
    }
}