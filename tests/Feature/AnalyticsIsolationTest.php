<?php

namespace Tests\Feature;

use App\Filament\Widgets\AffluenceChart;
use App\Filament\Widgets\BilletsChart;
use App\Filament\Widgets\CaParCompagnieChart;
use App\Filament\Widgets\CaParGareChart;
use App\Filament\Widgets\CaParVilleChart;
use App\Filament\Widgets\ChiffreAffairesChart;
use App\Filament\Widgets\ClientsChart;
use App\Filament\Widgets\CroissanceChart;
use App\Filament\Widgets\OperateursChart;
use App\Filament\Widgets\ProfilWidget;
use App\Filament\Widgets\RemplissageParRouteChart;
use App\Filament\Widgets\StatsOverview;
use App\Filament\Widgets\TopClientsTable;
use App\Filament\Widgets\TopRoutesChart;
use App\Models\Billet;
use App\Models\Compagnie;
use App\Models\Gare;
use App\Models\Horaire;
use App\Models\RouteTransport;
use App\Models\User;
use App\Models\Ville;
use App\Services\Analytics\VentesAnalytics;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AnalyticsIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Compagnie $compA;
    private Compagnie $compB;
    private Gare $gareAOuaga;
    private Gare $gareABobo;
    private Gare $gareBOuaga;

    /** @var array<string, User> */
    private array $users = [];

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $ouaga = Ville::create(['nom' => 'VilleOuaga', 'aliases' => []]);
        $bobo = Ville::create(['nom' => 'VilleBobo', 'aliases' => []]);

        $this->compA = Compagnie::create(['nom' => 'CompagnieA', 'slug' => 'compagnie-a', 'actif' => true]);
        $this->compB = Compagnie::create(['nom' => 'CompagnieB', 'slug' => 'compagnie-b', 'actif' => true]);

        $this->gareAOuaga = $this->gare($this->compA, $ouaga, 'GareAOuaga');
        $this->gareABobo = $this->gare($this->compA, $bobo, 'GareABobo');
        $this->gareBOuaga = $this->gare($this->compB, $ouaga, 'GareBOuaga');

        $client1 = $this->utilisateur('Client1', '77000001');
        $client2 = $this->utilisateur('Client2', '77000002');

        $this->vente($this->gareAOuaga, $client1, 'TA1', 5000);
        $this->vente($this->gareABobo, $client2, 'TA2', 7000);
        $this->vente($this->gareBOuaga, $client1, 'TB1', 11000);

        $this->users['super'] = $this->utilisateur('Super', '77100001');
        $this->users['super']->assignerRolePourCompagnie('super_admin', null);

        $this->users['adminA'] = $this->utilisateur('AdminA', '77100002');
        $this->users['adminA']->assignerRolePourCompagnie('admin_compagnie', $this->compA->id);

        $this->users['adminAOuaga'] = $this->utilisateur('AdminAOuaga', '77100003');
        $this->users['adminAOuaga']->assignerRolePourCompagnie('admin_compagnie', $this->compA->id, null, $ouaga->id);

        $this->users['adminB'] = $this->utilisateur('AdminB', '77100004');
        $this->users['adminB']->assignerRolePourCompagnie('admin_compagnie', $this->compB->id);

        $this->users['agentAOuaga'] = $this->utilisateur('AgentAOuaga', '77100005');
        $this->users['agentAOuaga']->assignerRolePourCompagnie('agent_gare', $this->compA->id, $this->gareAOuaga->id);

        $this->users['agentB'] = $this->utilisateur('AgentB', '77100006');
        $this->users['agentB']->assignerRolePourCompagnie('agent_gare', $this->compB->id, $this->gareBOuaga->id);
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

    private function vente(Gare $gare, User $client, string $reference, int $montant): void
    {
        $route = RouteTransport::create([
            'compagnie_id' => $gare->compagnie_id,
            'gare_depart_id' => $gare->id,
            'ville_arrivee_id' => $gare->ville_id,
            'prix' => $montant,
            'actif' => true,
        ]);

        Horaire::create(['route_transport_id' => $route->id, 'heure' => '08:00:00']);

        Billet::create([
            'reference' => $reference,
            'user_id' => $client->id,
            'route_transport_id' => $route->id,
            'gare_id' => $gare->id,
            'date_voyage' => now()->subDay()->toDateString(),
            'heure' => '08:00',
            'travelers_count' => 1,
            'montant_transport' => $montant,
            'taxe' => 0,
            'operateur_paiement' => 'test',
            'voyageurs' => [['nom' => 'Voyageur', 'code' => '1']],
            'date_paiement' => now()->subDays(2),
        ]);
    }

    private function analytics(string $cle): VentesAnalytics
    {
        return VentesAnalytics::pour($this->users[$cle]);
    }

    // ---------- Chiffres ----------

    public function test_le_chiffre_daffaires_suit_le_perimetre_de_chaque_role(): void
    {
        $attendus = [
            'super' => 23000,
            'adminA' => 12000,
            'adminAOuaga' => 5000,
            'adminB' => 11000,
            'agentAOuaga' => 5000,
            'agentB' => 11000,
        ];

        foreach ($attendus as $cle => $ca) {
            $resultat = $this->analytics($cle)->agregat(now()->subDays(30), now());

            $this->assertEqualsWithDelta($ca, (float) $resultat->ca, 0.01, "CA incorrect pour {$cle}");
        }
    }

    public function test_aucun_classement_ne_deborde_du_perimetre(): void
    {
        [$labels, $valeurs] = $this->analytics('adminAOuaga')->caParCompagnie('tout');
        $this->assertSame(['CompagnieA'], $labels);
        $this->assertSame([5000.0], $valeurs);

        [$labels, $valeurs] = $this->analytics('adminAOuaga')->caParGare('tout');
        $this->assertSame(['GareAOuaga'], $labels);
        $this->assertSame([5000.0], $valeurs);

        [$labels] = $this->analytics('agentB')->caParGare('tout');
        $this->assertSame(['GareBOuaga'], $labels);

        [$labels] = $this->analytics('adminA')->caParGare('tout');
        sort($labels);
        $this->assertSame(['GareABobo', 'GareAOuaga'], $labels);

        [$labels] = $this->analytics('adminA')->caParRoute('tout');
        foreach ($labels as $label) {
            $this->assertStringNotContainsString('GareB', $label);
        }
    }

    public function test_le_top_des_clients_ne_somme_que_le_perimetre(): void
    {
        $filtre = $this->analytics('adminAOuaga')->filtreBillets();

        $totaux = User::query()
            ->whereHas('billets', $filtre)
            ->withSum(['billets as ca_total' => $filtre], 'montant_transport')
            ->get()
            ->mapWithKeys(fn ($u) => [$u->telephone => (float) $u->ca_total])
            ->all();

        // Client1 a aussi acheté chez la compagnie B (11 000) : cela ne doit pas apparaître.
        $this->assertSame(['77000001' => 5000.0], $totaux);
    }

    public function test_toutes_les_requetes_danalyse_fonctionnent_pour_chaque_role(): void
    {
        foreach (array_keys($this->users) as $cle) {
            $a = $this->analytics($cle);

            foreach (array_keys(VentesAnalytics::GRANULARITES) as $granularite) {
                $this->assertIsArray($a->serie($granularite, 'SUM(b.montant_transport)'), "serie {$granularite} / {$cle}");
                $this->assertIsArray($a->clients($granularite), "clients {$granularite} / {$cle}");
            }

            foreach (array_keys(VentesAnalytics::FENETRES) as $fenetre) {
                $this->assertIsArray($a->caParGare($fenetre));
                $this->assertIsArray($a->caParVille($fenetre));
                $this->assertIsArray($a->caParRoute($fenetre));
                $this->assertIsArray($a->caParCompagnie($fenetre));
                $this->assertIsArray($a->caParOperateur($fenetre));
            }

            $this->assertIsArray($a->croissanceMensuelle());
            $this->assertIsArray($a->affluence('jour'));
            $this->assertIsArray($a->affluence('heure'));
            $this->assertIsArray($a->remplissage(now()->subDays(30), now()->subDay()));
            $this->assertIsArray($a->remplissageParRoute('top'));
            $this->assertIsArray($a->remplissageParRoute('bas'));
        }
    }

    // ---------- Widgets ----------

    public function test_les_widgets_respectent_les_roles_et_se_rendent(): void
    {
        $tous = ['super_admin', 'admin_compagnie', 'agent_gare'];

        $matrice = [
            ProfilWidget::class => $tous,
            StatsOverview::class => $tous,
            ChiffreAffairesChart::class => $tous,
            CroissanceChart::class => $tous,
            BilletsChart::class => $tous,
            ClientsChart::class => $tous,
            CaParGareChart::class => ['super_admin', 'admin_compagnie'],
            CaParVilleChart::class => ['super_admin', 'admin_compagnie'],
            TopRoutesChart::class => ['super_admin', 'admin_compagnie'],
            CaParCompagnieChart::class => ['super_admin'],
            OperateursChart::class => $tous,
            AffluenceChart::class => $tous,
            TopClientsTable::class => $tous,
            RemplissageParRouteChart::class => $tous,
        ];

        $profils = ['super' => 'super_admin', 'adminAOuaga' => 'admin_compagnie', 'agentAOuaga' => 'agent_gare'];

        foreach ($profils as $cle => $role) {
            $this->actingAs($this->users[$cle]);

            foreach ($matrice as $classe => $rolesAutorises) {
                $autorise = in_array($role, $rolesAutorises, true);

                $this->assertSame($autorise, $classe::canView(), "{$classe} pour {$role}");

                if ($autorise) {
                    Livewire::test($classe)->assertOk();
                }
            }
        }
    }
}