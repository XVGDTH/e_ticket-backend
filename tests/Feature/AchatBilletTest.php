<?php

namespace Tests\Feature;

use App\Models\Billet;
use App\Models\Compagnie;
use App\Models\Gare;
use App\Models\Horaire;
use App\Models\RouteTransport;
use App\Models\User;
use App\Models\Ville;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AchatBilletTest extends TestCase
{
    use RefreshDatabase;

    private const MOT_DE_PASSE = 'motdepasse123';

    private Compagnie $compagnie;
    private Ville $ville;
    private Gare $gare;
    private Gare $autreGare;
    private RouteTransport $route;
    private string $jeton;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ville = Ville::create(['nom' => 'VilleTest', 'aliases' => []]);
        $this->compagnie = Compagnie::create(['nom' => 'CompagnieTest', 'slug' => 'compagnie-test', 'actif' => true]);
        $this->gare = Gare::create(['compagnie_id' => $this->compagnie->id, 'ville_id' => $this->ville->id, 'quartier' => 'GareATest']);
        $this->autreGare = Gare::create(['compagnie_id' => $this->compagnie->id, 'ville_id' => $this->ville->id, 'quartier' => 'GareBTest']);

        $this->route = RouteTransport::create([
            'compagnie_id' => $this->compagnie->id,
            'gare_depart_id' => $this->gare->id,
            'ville_arrivee_id' => $this->ville->id,
            'prix' => 5000,
            'actif' => true,
        ]);

        Horaire::create(['route_transport_id' => $this->route->id, 'heure' => '08:00:00']);
        Horaire::create(['route_transport_id' => $this->route->id, 'heure' => '00:01:00']);

        $client = $this->utilisateur('Client', '80000000');
        $this->jeton = $client->createToken('test', ['client'])->plainTextToken;
    }

    // ---------- Préparation ----------

    private function utilisateur(string $prenom, string $telephone): User
    {
        return User::create([
            'prenom' => $prenom,
            'nom' => 'Test',
            'telephone' => $telephone,
            'telephone_verifie' => true,
            'password' => self::MOT_DE_PASSE,
        ]);
    }

    private function api(string $jeton): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($jeton);
    }

    private function achat(array $surcharge = [])
    {
        return $this->api($this->jeton)->postJson('/api/billets', array_merge([
            'route_transport_id' => $this->route->id,
            'date_voyage' => today()->addDay()->toDateString(),
            'heure' => '08h00',
            'travelers_count' => 2,
            'operateur_paiement' => 'Orange Money',
            'voyageurs' => ['Awa Traoré', 'Issa Ouédraogo'],
        ], $surcharge));
    }

    // ---------- Achat ----------

    public function test_le_prix_est_calcule_par_le_serveur(): void
    {
        $this->achat(['montant_transport' => 1, 'taxe' => 0])
            ->assertStatus(422)
            ->assertJsonPath('montant_attendu', 10000);

        $this->achat()
            ->assertOk()
            ->assertJsonPath('billet.montant_transport', 10000)
            ->assertJsonPath('billet.gare_id', $this->gare->id);
    }

    public function test_route_ou_compagnie_inactive_refusee(): void
    {
        $this->route->update(['actif' => false]);
        $this->achat()->assertStatus(422);

        $this->route->update(['actif' => true]);
        $this->compagnie->update(['actif' => false]);
        $this->achat()->assertStatus(422);
    }

    public function test_les_donnees_incoherentes_sont_refusees(): void
    {
        $this->achat(['gare_id' => $this->autreGare->id])->assertStatus(422);
        $this->achat(['travelers_count' => 3])->assertStatus(422);
        $this->achat(['heure' => '09h00'])->assertStatus(422);
        $this->achat(['date_voyage' => today()->subDay()->toDateString()])->assertStatus(422);
        $this->achat(['date_voyage' => today()->toDateString(), 'heure' => '00h01'])->assertStatus(422);
    }

    public function test_un_depart_ne_peut_pas_depasser_sa_capacite(): void
    {
        config(['analytics.places_par_depart' => 3]);

        $this->achat()->assertOk();

        $this->achat()->assertStatus(409)->assertJsonPath('places_restantes', 1);

        $this->achat(['travelers_count' => 1, 'voyageurs' => ['Moussa Sawadogo']])->assertOk();

        $this->achat(['travelers_count' => 1, 'voyageurs' => ['Fatou Kaboré']])
            ->assertStatus(409)
            ->assertJsonPath('places_restantes', 0);
    }

    public function test_les_codes_des_passagers_ne_sont_pas_devinables(): void
    {
        $billet = $this->achat()->assertOk()->json('billet');

        foreach ($billet['voyageurs'] as $i => $voyageur) {
            $ancienCalcul = strtoupper(substr(hash('sha256', "{$billet['reference']}|$i|{$voyageur['nom']}|CONVERGE_SAS_V1"), 0, 8));

            $this->assertSame(8, strlen($voyageur['code']));
            $this->assertNotSame($ancienCalcul, $voyageur['code']);
        }
    }

    // ---------- Contrôle ----------

    public function test_le_controle_est_reserve_a_lagent_de_la_gare(): void
    {
        $this->postJson('/api/billets/verifier', ['reference' => 'X', 'nom' => 'X', 'code' => 'X'])->assertNotFound();

        $billetJson = $this->achat()->assertOk()->json('billet');
        $billet = Billet::where('reference', $billetJson['reference'])->firstOrFail();
        $billet->update(['date_voyage' => today()->toDateString()]);

        $agentA = $this->utilisateur('AgentA', '80000001');
        $agentA->assignerRolePourCompagnie('agent_gare', $this->compagnie->id, $this->gare->id);

        $agentB = $this->utilisateur('AgentB', '80000002');
        $agentB->assignerRolePourCompagnie('agent_gare', $this->compagnie->id, $this->autreGare->id);

        $jetonA = $this->postJson('/api/agent/login', ['telephone' => '80000001', 'password' => self::MOT_DE_PASSE])->json('token');
        $jetonB = $this->postJson('/api/agent/login', ['telephone' => '80000002', 'password' => self::MOT_DE_PASSE])->json('token');

        $voyageur = $billetJson['voyageurs'][0];
        $demande = ['reference' => $billetJson['reference'], 'nom' => $voyageur['nom'], 'code' => $voyageur['code']];

        $this->api($jetonA)->postJson('/api/agent/billets/verifier', $demande)->assertOk()->assertJsonPath('valide', true);

        $this->api($jetonA)->postJson('/api/agent/billets/verifier', array_merge($demande, ['code' => 'FAUX0000']))
            ->assertOk()->assertJsonPath('valide', false);

        // L'agent d'une autre gare ne voit même pas que ce billet existe.
        $this->api($jetonB)->postJson('/api/agent/billets/verifier', $demande)->assertOk()->assertJsonPath('valide', false);
    }

    // ---------- Inscription et connexion ----------

    public function test_un_numero_non_verifie_ne_peut_ni_etre_reserve_ni_se_connecter(): void
    {
        $inscription = ['prenom' => 'Awa', 'nom' => 'Traoré', 'telephone' => '80000050', 'password' => 'secret123'];

        $this->postJson('/api/auth/register', $inscription)->assertOk();

        $this->postJson('/api/auth/login', ['telephone' => '80000050', 'password' => 'secret123'])
            ->assertStatus(403)
            ->assertJsonPath('code', 'telephone_non_verifie');

        // Une nouvelle inscription du même numéro (pas encore vérifié) remplace la précédente.
        $this->postJson('/api/auth/register', array_merge($inscription, ['password' => 'autre-secret456']))->assertOk();
        $this->assertSame(1, User::where('telephone', '80000050')->count());

        // Un numéro vérifié ne peut plus être réinscrit.
        User::where('telephone', '80000050')->firstOrFail()->update(['telephone_verifie' => true]);
        $this->postJson('/api/auth/register', $inscription)->assertStatus(422);

        $this->postJson('/api/auth/login', ['telephone' => '80000050', 'password' => 'autre-secret456'])->assertOk()->assertJsonStructure(['token']);
    }

    public function test_trop_de_mots_de_passe_faux_bloquent_la_connexion(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', ['telephone' => '80000000', 'password' => 'faux'])->assertStatus(401);
        }

        $this->postJson('/api/auth/login', ['telephone' => '80000000', 'password' => self::MOT_DE_PASSE])->assertStatus(429);
    }

    public function test_les_clients_napparaissent_pas_dans_la_liste_des_utilisateurs_du_panneau(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->postJson('/api/auth/register', [
            'prenom' => 'ClientVisible', 'nom' => 'Test', 'telephone' => '80000060', 'password' => 'secret123',
        ])->assertOk();

        $super = $this->utilisateur('SuperTest', '80000070');
        $super->assignerRolePourCompagnie('super_admin', null);

        $this->actingAs($super)->get('/admin/users')->assertOk()->assertSee('SuperTest')->assertDontSee('ClientVisible');
    }
}