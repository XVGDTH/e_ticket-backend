<?php

namespace Tests\Feature;

use App\Filament\Resources\Billets\BilletResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\AvisGare;
use App\Models\Billet;
use App\Models\Compagnie;
use App\Models\Gare;
use App\Models\RouteTransport;
use App\Models\User;
use App\Models\Ville;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BilletsParGareTest extends TestCase
{
    use RefreshDatabase;

    private Compagnie $rahimo;
    private Compagnie $elitis;
    private Gare $kalgondin;
    private Gare $pissy;
    private Gare $boboRahimo;
    private Gare $zadElitis;
    private User $client;

    /** @var array<string, User> */
    private array $staff = [];

    protected function setUp(): void
    {
        parent::setUp();

        $ouaga = Ville::create(['nom' => 'VilleOuaga', 'aliases' => []]);
        $bobo = Ville::create(['nom' => 'VilleBobo', 'aliases' => []]);

        $this->rahimo = Compagnie::create(['nom' => 'RahimoTest', 'slug' => 'rahimo-test', 'actif' => true]);
        $this->elitis = Compagnie::create(['nom' => 'ElitisTest', 'slug' => 'elitis-test', 'actif' => true]);

        $this->kalgondin = $this->gare($this->rahimo, $ouaga, 'KalgondinTest');
        $this->pissy = $this->gare($this->rahimo, $ouaga, 'PissyTest');
        $this->boboRahimo = $this->gare($this->rahimo, $bobo, 'BoboBoulevardTest');
        $this->zadElitis = $this->gare($this->elitis, $ouaga, 'ZadElitisTest');

        $this->client = $this->utilisateur('Client', '78000000');

        $this->billet($this->kalgondin, 'REFKALGONDIN');
        $this->billet($this->pissy, 'REFPISSY');
        $this->billet($this->boboRahimo, 'REFBOBO');
        $this->billet($this->zadElitis, 'REFZAD');

        $this->staff['super'] = $this->utilisateur('Super', '78100001');
        $this->staff['super']->assignerRolePourCompagnie('super_admin', null);

        $this->staff['adminOuaga'] = $this->utilisateur('AdminOuaga', '78100002');
        $this->staff['adminOuaga']->assignerRolePourCompagnie('admin_compagnie', $this->rahimo->id, null, $ouaga->id);

        $this->staff['adminRahimo'] = $this->utilisateur('AdminRahimo', '78100003');
        $this->staff['adminRahimo']->assignerRolePourCompagnie('admin_compagnie', $this->rahimo->id);

        $this->staff['adminElitis'] = $this->utilisateur('AdminElitis', '78100004');
        $this->staff['adminElitis']->assignerRolePourCompagnie('admin_compagnie', $this->elitis->id);

        foreach ([
            'agentKalgondin' => [$this->rahimo, $this->kalgondin],
            'agentPissy' => [$this->rahimo, $this->pissy],
            'agentBobo' => [$this->rahimo, $this->boboRahimo],
            'agentZad' => [$this->elitis, $this->zadElitis],
        ] as $cle => [$compagnie, $gare]) {
            $this->staff[$cle] = $this->utilisateur($cle, '7820' . count($this->staff));
            $this->staff[$cle]->assignerRolePourCompagnie('agent_gare', $compagnie->id, $gare->id);
        }
    }

    // ---------- Préparation ----------

    private function utilisateur(string $prenom, string $telephone): User
    {
        return User::create([
            'prenom' => $prenom,
            'nom' => 'Test',
            'telephone' => $telephone,
            'telephone_verifie' => true,
            'password' => 'motdepasse123',
        ]);
    }

    private function gare(Compagnie $compagnie, Ville $ville, string $quartier): Gare
    {
        return Gare::create(['compagnie_id' => $compagnie->id, 'ville_id' => $ville->id, 'quartier' => $quartier]);
    }

    private function route(Gare $gare): RouteTransport
    {
        return RouteTransport::create([
            'compagnie_id' => $gare->compagnie_id,
            'gare_depart_id' => $gare->id,
            'ville_arrivee_id' => $gare->ville_id,
            'prix' => 5000,
            'actif' => true,
        ]);
    }

    private function nouveauBillet(RouteTransport $route, int $gareEnvoyee, string $reference): Billet
    {
        return Billet::create([
            'reference' => $reference,
            'user_id' => $this->client->id,
            'route_transport_id' => $route->id,
            'gare_id' => $gareEnvoyee,
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

    private function billet(Gare $gare, string $reference): Billet
    {
        return $this->nouveauBillet($this->route($gare), $gare->id, $reference);
    }

    private function references(User $user): array
    {
        return Billet::visiblesPar($user)->pluck('reference')->sort()->values()->all();
    }

    // ---------- Visibilité ----------

    public function test_chaque_role_ne_recoit_que_les_billets_de_son_perimetre(): void
    {
        $attendus = [
            'agentKalgondin' => ['REFKALGONDIN'],
            'agentPissy' => ['REFPISSY'],
            'agentBobo' => ['REFBOBO'],
            'agentZad' => ['REFZAD'],
            'adminOuaga' => ['REFKALGONDIN', 'REFPISSY'],
            'adminRahimo' => ['REFBOBO', 'REFKALGONDIN', 'REFPISSY'],
            'adminElitis' => ['REFZAD'],
            'super' => ['REFBOBO', 'REFKALGONDIN', 'REFPISSY', 'REFZAD'],
        ];

        foreach ($attendus as $cle => $references) {
            $this->assertSame($references, $this->references($this->staff[$cle]), "Billets visibles pour {$cle}");
        }
    }

    public function test_le_panneau_de_lagent_ne_montre_que_les_billets_de_sa_gare(): void
    {
        $propres = [
            'agentKalgondin' => 'REFKALGONDIN',
            'agentPissy' => 'REFPISSY',
            'agentBobo' => 'REFBOBO',
            'agentZad' => 'REFZAD',
        ];

        foreach ($propres as $cle => $reference) {
            $reponse = $this->actingAs($this->staff[$cle])->get('/admin/billets')->assertOk()->assertSee($reference);

            foreach (array_diff($propres, [$reference]) as $autre) {
                $reponse->assertDontSee($autre);
            }
        }
    }

    public function test_le_panneau_et_la_regle_centrale_donnent_les_memes_billets(): void
    {
        foreach ($this->staff as $cle => $user) {
            $this->actingAs($user);

            $panneau = BilletResource::getEloquentQuery()->pluck('id')->sort()->values()->all();
            $regle = Billet::visiblesPar($user)->pluck('id')->sort()->values()->all();

            $this->assertSame($regle, $panneau, "Écart panneau / règle centrale pour {$cle}");
        }
    }

    // ---------- Intégrité ----------

    public function test_un_billet_pris_a_pissy_ne_va_jamais_chez_kalgondin(): void
    {
        $routePissy = $this->route($this->pissy);

        // L'application envoie (par erreur ou par fraude) la gare de Kalgondin.
        $billet = $this->nouveauBillet($routePissy, $this->kalgondin->id, 'REFPISSY2');

        $this->assertSame($this->pissy->id, (int) $billet->gare_id);
        $this->assertNotContains('REFPISSY2', $this->references($this->staff['agentKalgondin']));
        $this->assertContains('REFPISSY2', $this->references($this->staff['agentPissy']));
    }

    public function test_un_billet_emis_ne_change_ni_de_route_ni_de_gare(): void
    {
        $billet = Billet::where('reference', 'REFPISSY')->firstOrFail();

        $billet->update(['gare_id' => $this->kalgondin->id]);

        $this->assertSame($this->pissy->id, (int) $billet->fresh()->gare_id);
    }

    public function test_un_avis_est_toujours_rattache_a_la_gare_du_billet(): void
    {
        $billet = Billet::where('reference', 'REFPISSY')->firstOrFail();

        $avis = AvisGare::create([
            'billet_id' => $billet->id,
            'gare_id' => $this->kalgondin->id, // gare envoyée par erreur
            'scores' => ['accueil' => 5],
            'commentaire' => 'Test',
        ]);

        $this->assertSame($this->pissy->id, (int) $avis->gare_id);
    }

    public function test_la_base_refuse_un_billet_dont_la_gare_nest_pas_celle_de_sa_route(): void
    {
        $route = RouteTransport::where('gare_depart_id', $this->pissy->id)->firstOrFail();

        try {
            DB::table('billets')->insert([
                'reference' => 'REFFRAUDE',
                'user_id' => $this->client->id,
                'route_transport_id' => $route->id,
                'gare_id' => $this->zadElitis->id,
                'date_voyage' => '2026-10-20',
                'heure' => '08:00',
                'travelers_count' => 1,
                'montant_transport' => 5000,
                'taxe' => 0,
                'operateur_paiement' => 'test',
                'voyageurs' => '[]',
                'date_paiement' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->fail('La base aurait dû refuser ce billet.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('foreign key', strtolower($e->getMessage()));
        }
    }

    public function test_la_base_refuse_une_route_partant_dune_gare_dune_autre_compagnie(): void
    {
        try {
            RouteTransport::create([
                'compagnie_id' => $this->elitis->id,
                'gare_depart_id' => $this->kalgondin->id, // gare de Rahimo
                'ville_arrivee_id' => $this->kalgondin->ville_id,
                'prix' => 5000,
                'actif' => true,
            ]);

            $this->fail('La base aurait dû refuser cette route.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('foreign key', strtolower($e->getMessage()));
        }
    }

    // ---------- Garde-fou de code ----------

    public function test_aucune_ressource_ne_contourne_le_filtre_central(): void
    {
        $fichiers = glob(app_path('Filament/Resources/*/*Resource.php'));

        $this->assertNotEmpty($fichiers);

        foreach ($fichiers as $fichier) {
            $classe = 'App\\Filament\\Resources\\' . basename(dirname($fichier)) . '\\' . basename($fichier, '.php');

            // Volontaire : les comptes staff ont leur propre filtre (rôle + gare + ville).
            if ($classe === UserResource::class) {
                continue;
            }

            $source = basename((string) (new \ReflectionMethod($classe, 'getEloquentQuery'))->getFileName());

            $this->assertSame('ScopedToCompagnie.php', $source, "{$classe} contourne le filtre central.");
        }
    }
}