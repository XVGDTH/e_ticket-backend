<?php

namespace Tests\Feature;

use App\Enums\BilletStatut;
use App\Models\Billet;
use App\Models\Compagnie;
use App\Models\Gare;
use App\Models\RouteTransport;
use App\Models\User;
use App\Models\Ville;
use App\Services\Analytics\VentesAnalytics;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentApiTest extends TestCase
{
    use RefreshDatabase;

    private const MOT_DE_PASSE = 'motdepasse123';

    private Compagnie $compagnie;
    private Gare $gareA;
    private Gare $gareB;
    private User $agentA;
    private User $admin;
    private User $client;

    protected function setUp(): void
    {
        parent::setUp();

        $ville = Ville::create(['nom' => 'VilleTest', 'aliases' => []]);
        $this->compagnie = Compagnie::create(['nom' => 'CompagnieTest', 'slug' => 'compagnie-test', 'actif' => true]);
        $this->gareA = Gare::create(['compagnie_id' => $this->compagnie->id, 'ville_id' => $ville->id, 'quartier' => 'GareATest']);
        $this->gareB = Gare::create(['compagnie_id' => $this->compagnie->id, 'ville_id' => $ville->id, 'quartier' => 'GareBTest']);

        $this->client = $this->utilisateur('Client', '79000000');

        $this->agentA = $this->utilisateur('AgentA', '79000001');
        $this->agentA->assignerRolePourCompagnie('agent_gare', $this->compagnie->id, $this->gareA->id);

        $this->admin = $this->utilisateur('Admin', '79000003');
        $this->admin->assignerRolePourCompagnie('admin_compagnie', $this->compagnie->id);

        $this->billet($this->gareA, 'REFA1');
        $this->billet($this->gareA, 'REFADEMAIN', today()->addDay()->toDateString());
        $this->billet($this->gareB, 'REFB1');
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

    private function billet(Gare $gare, string $reference, ?string $date = null): Billet
    {
        $route = RouteTransport::create([
            'compagnie_id' => $gare->compagnie_id,
            'gare_depart_id' => $gare->id,
            'ville_arrivee_id' => $gare->ville_id,
            'prix' => 5000,
            'actif' => true,
        ]);

        return Billet::create([
            'reference' => $reference,
            'user_id' => $this->client->id,
            'route_transport_id' => $route->id,
            'gare_id' => $gare->id,
            'date_voyage' => $date ?? today()->toDateString(),
            'heure' => '08:00',
            'travelers_count' => 1,
            'montant_transport' => 5000,
            'taxe' => 0,
            'operateur_paiement' => 'test',
            'voyageurs' => [['nom' => 'Voyageur', 'code' => '1']],
            'date_paiement' => now()->subHour(),
        ]);
    }

    private function jeton(User $agent): string
    {
        return $this->postJson('/api/agent/login', [
            'telephone' => $agent->telephone,
            'password' => self::MOT_DE_PASSE,
        ])->json('token');
    }

    /** Une requête authentifiée avec ce jeton, sans mémoire de la requête précédente. */
    private function api(string $jeton): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($jeton);
    }

    // ---------- Connexion ----------

    public function test_seuls_les_agents_actifs_se_connectent(): void
    {
        $this->postJson('/api/agent/login', ['telephone' => '79000001', 'password' => self::MOT_DE_PASSE])
            ->assertOk()
            ->assertJsonPath('agent.gare.quartier', 'GareATest')
            ->assertJsonStructure(['token']);

        $this->postJson('/api/agent/login', ['telephone' => '79000001', 'password' => 'faux'])->assertStatus(422);

        // Un client, même avec le bon mot de passe, n'entre pas ; un admin non plus.
        $this->postJson('/api/agent/login', ['telephone' => '79000000', 'password' => self::MOT_DE_PASSE])->assertStatus(422);
        $this->postJson('/api/agent/login', ['telephone' => '79000003', 'password' => self::MOT_DE_PASSE])->assertStatus(422);

        $this->agentA->update(['actif' => false]);
        $this->postJson('/api/agent/login', ['telephone' => '79000001', 'password' => self::MOT_DE_PASSE])->assertStatus(422);
    }

    public function test_un_client_ou_un_admin_ne_peut_pas_utiliser_lapi_agent(): void
    {
        $jetonClient = $this->client->createToken('client')->plainTextToken;
        $jetonAdmin = $this->admin->createToken('admin')->plainTextToken;

        $this->api($jetonClient)->getJson('/api/agent/billets')->assertForbidden();
        $this->api($jetonAdmin)->getJson('/api/agent/billets')->assertForbidden();
    }

    public function test_un_mot_de_passe_temporaire_bloque_tout_sauf_le_changement(): void
    {
        $this->agentA->forceFill(['doit_changer_mot_de_passe' => true])->save();
        $jeton = $this->jeton($this->agentA);

        $this->api($jeton)->getJson('/api/agent/billets')->assertForbidden()->assertJsonPath('code', 'mot_de_passe_a_changer');
        $this->api($jeton)->getJson('/api/agent/me')->assertOk();

        $this->api($jeton)->postJson('/api/agent/mot-de-passe', [
            'mot_de_passe_actuel' => self::MOT_DE_PASSE,
            'nouveau_mot_de_passe' => 'NouveauMdp2026',
            'nouveau_mot_de_passe_confirmation' => 'NouveauMdp2026',
        ])->assertOk();

        $this->api($jeton)->getJson('/api/agent/billets')->assertOk();
    }

    // ---------- Cloisonnement par gare ----------

    public function test_lagent_ne_voit_que_les_billets_de_sa_gare(): void
    {
        $jeton = $this->jeton($this->agentA);

        $references = collect($this->api($jeton)->getJson('/api/agent/billets')->assertOk()->json('billets'))
            ->pluck('reference')->all();

        $this->assertSame(['REFA1'], $references); // pas REFB1 (autre gare), pas REFADEMAIN (demain)

        $this->api($jeton)->getJson('/api/agent/billets/REFA1')->assertOk()->assertJsonPath('billet.reference', 'REFA1');
        $this->api($jeton)->getJson('/api/agent/billets/REFB1')->assertNotFound();
        $this->api($jeton)->postJson('/api/agent/billets/REFB1/valider')->assertNotFound();

        $this->assertSame(BilletStatut::Valide, Billet::where('reference', 'REFB1')->firstOrFail()->statut);
    }

    // ---------- Statuts ----------

    public function test_validation_a_lembarquement_une_seule_fois(): void
    {
        $jeton = $this->jeton($this->agentA);

        $this->api($jeton)->postJson('/api/agent/billets/REFA1/valider')
            ->assertOk()
            ->assertJsonPath('billet.statut.valeur', 'utilise');

        $this->api($jeton)->postJson('/api/agent/billets/REFA1/valider')->assertStatus(422);

        $billet = Billet::where('reference', 'REFA1')->firstOrFail();

        $this->assertSame(BilletStatut::Utilise, $billet->statut);
        $this->assertSame(2, $billet->evenements()->count()); // achat + validation
        $this->assertSame($this->agentA->id, (int) $billet->evenements()->latest('id')->first()->user_id);
    }

    public function test_un_billet_dun_autre_jour_ne_peut_pas_etre_valide(): void
    {
        $jeton = $this->jeton($this->agentA);

        $this->api($jeton)->postJson('/api/agent/billets/REFADEMAIN/valider')->assertStatus(422);

        $this->assertSame(BilletStatut::Valide, Billet::where('reference', 'REFADEMAIN')->firstOrFail()->statut);
    }

    public function test_annulation_par_ladmin_sortie_du_chiffre_daffaires_et_validation_impossible(): void
    {
        $billet = Billet::where('reference', 'REFA1')->firstOrFail();
        $analytics = VentesAnalytics::pour($this->admin);

        $avant = (float) $analytics->agregat(now()->subDays(30), now()->addDay())->ca;

        $billet->changerStatut(BilletStatut::Annule, $this->admin, 'Client absent, remboursement demandé');

        $apres = (float) $analytics->agregat(now()->subDays(30), now()->addDay())->ca;

        $this->assertEqualsWithDelta($avant - 5000, $apres, 0.01);

        $jeton = $this->jeton($this->agentA);
        $this->api($jeton)->postJson('/api/agent/billets/REFA1/valider')->assertStatus(422);
    }

    public function test_un_agent_ne_peut_pas_annuler_et_le_statut_ne_se_modifie_pas_a_la_main(): void
    {
        $billet = Billet::where('reference', 'REFA1')->firstOrFail();

        try {
            $billet->changerStatut(BilletStatut::Annule, $this->agentA, 'Tentative non autorisée');
            $this->fail('Un agent ne doit pas pouvoir annuler un billet.');
        } catch (AuthorizationException $e) {
            $this->addToAssertionCount(1);
        }

        // Modification directe : annulée par le modèle.
        $billet = Billet::where('reference', 'REFA1')->firstOrFail();
        $billet->statut = BilletStatut::Rembourse;
        $billet->save();

        $this->assertSame(BilletStatut::Valide, $billet->fresh()->statut);
    }
}

