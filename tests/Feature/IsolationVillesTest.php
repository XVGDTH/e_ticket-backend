<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Models\Billet;
use App\Models\Compagnie;
use App\Models\Gare;
use App\Models\RouteTransport;
use App\Models\User;
use App\Models\Ville;
use App\Services\Analytics\VentesAnalytics;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class IsolationVillesTest extends TestCase
{
    use RefreshDatabase;

    private Compagnie $staf;
    private Ville $ouaga;
    private Ville $bobo;
    private Gare $gareOuaga;
    private Gare $gareBobo;
    private User $adminOuaga;
    private User $adminSansVille;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->staf = Compagnie::create(['nom' => 'StafTest', 'slug' => 'staf-test', 'actif' => true]);
        $this->ouaga = Ville::create(['nom' => 'VilleOuaga', 'aliases' => []]);
        $this->bobo = Ville::create(['nom' => 'VilleBobo', 'aliases' => []]);

        $this->gareOuaga = Gare::create(['compagnie_id' => $this->staf->id, 'ville_id' => $this->ouaga->id, 'quartier' => 'GareOuagaTest']);
        $this->gareBobo = Gare::create(['compagnie_id' => $this->staf->id, 'ville_id' => $this->bobo->id, 'quartier' => 'GareBoboTest']);

        $client = $this->creerUtilisateur('Client', '76000000');

        $this->creerBillet($this->gareOuaga, $client, 'BILOUAGA');
        $this->creerBillet($this->gareBobo, $client, 'BILBOBO');

        $this->adminOuaga = $this->creerUtilisateur('AdminOuaga', '76000001');
        $this->adminOuaga->assignerRolePourCompagnie('admin_compagnie', $this->staf->id, null, $this->ouaga->id);

        $this->adminSansVille = $this->creerUtilisateur('AdminToutes', '76000002');
        $this->adminSansVille->assignerRolePourCompagnie('admin_compagnie', $this->staf->id);
    }

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

    private function creerBillet(Gare $gare, User $client, string $reference): void
    {
        $route = RouteTransport::create([
            'compagnie_id' => $gare->compagnie_id,
            'gare_depart_id' => $gare->id,
            'ville_arrivee_id' => $gare->ville_id,
            'prix' => 5000,
            'actif' => true,
        ]);

        Billet::create([
            'reference' => $reference,
            'user_id' => $client->id,
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
    }

    public function test_admin_de_ville_ne_voit_que_les_gares_de_sa_ville(): void
    {
        $this->actingAs($this->adminOuaga)
            ->get('/admin/gares')
            ->assertOk()
            ->assertSee('GareOuagaTest')
            ->assertDontSee('GareBoboTest');
    }

    public function test_admin_de_ville_ne_peut_pas_ouvrir_une_gare_dune_autre_ville(): void
    {
        $this->actingAs($this->adminOuaga)
            ->get('/admin/gares/' . $this->gareBobo->id . '/edit')
            ->assertNotFound();
    }

    public function test_admin_de_ville_ne_voit_que_les_routes_et_billets_de_sa_ville(): void
    {
        $this->actingAs($this->adminOuaga)
            ->get('/admin/route-transports')
            ->assertOk()
            ->assertSee('GareOuagaTest')
            ->assertDontSee('GareBoboTest');

        $this->actingAs($this->adminOuaga)
            ->get('/admin/billets')
            ->assertOk()
            ->assertSee('BILOUAGA')
            ->assertDontSee('BILBOBO');
    }

    public function test_admin_sans_ville_voit_toute_la_compagnie(): void
    {
        $this->actingAs($this->adminSansVille)
            ->get('/admin/gares')
            ->assertOk()
            ->assertSee('GareOuagaTest')
            ->assertSee('GareBoboTest');
    }

    public function test_une_gare_creee_par_un_admin_de_ville_reste_dans_sa_ville(): void
    {
        $this->actingAs($this->adminOuaga);

        $gare = Gare::create([
            'compagnie_id' => $this->staf->id,
            'ville_id' => $this->bobo->id, // tentative de fraude
            'quartier' => 'GareInfiltree',
        ]);

        $this->assertSame($this->ouaga->id, (int) $gare->ville_id);
    }

    public function test_admin_de_ville_ne_peut_pas_creer_un_agent_dans_une_autre_ville(): void
    {
        $this->actingAs($this->adminOuaga);

        try {
            Livewire::test(CreateUser::class)
                ->fillForm([
                    'prenom' => 'Intrus',
                    'nom' => 'Test',
                    'telephone' => '76000099',
                    'password' => 'motdepasse123',
                    'role' => 'agent_gare',
                    'gare_id' => $this->gareBobo->id,
                ])
                ->call('create');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        $this->assertDatabaseMissing('users', ['telephone' => '76000099']);
    }

    public function test_les_chiffres_suivent_la_ville_de_ladmin(): void
    {
        $this->assertSame(1, VentesAnalytics::pour($this->adminOuaga)->base()->count());
        $this->assertSame(2, VentesAnalytics::pour($this->adminSansVille)->base()->count());
    }
}