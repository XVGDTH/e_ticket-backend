<?php

namespace Tests\Feature;

use App\Models\BulletinPaie;
use App\Models\Compagnie;
use App\Models\Employe;
use App\Models\User;
use Database\Seeders\DemoPersonnelSeeder;
use Database\Seeders\SupprimerDonneesDemoSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoPersonnelSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        config(['securite.mfa_obligatoire_admins' => false]);
    }

    public function test_cree_un_employe_et_son_bulletin_fictifs_sans_generer_la_paie_des_autres_employes(): void
    {
        $compagnie = Compagnie::factory()->create();
        $administrateur = User::create([
            'prenom' => 'Admin',
            'nom' => 'Démo',
            'telephone' => '70000001',
            'telephone_verifie' => true,
            'password' => 'motdepasse123',
        ]);
        $administrateur->assignerRolePourCompagnie('super_admin');
        $this->actingAs($administrateur);

        (new DemoPersonnelSeeder)->run();

        $employe = Employe::query()->where('matricule', 'DEMO-RH-0001')->firstOrFail();
        $bulletin = BulletinPaie::query()->where('employe_id', $employe->id)->firstOrFail();

        $this->assertSame((int) $compagnie->id, (int) $employe->compagnie_id);
        $this->assertSame('DEMO', $employe->nom);
        $this->assertSame(250000, (int) $bulletin->salaire_base);
        $this->assertGreaterThan(0, (int) $bulletin->net_a_payer);
        $this->assertDatabaseCount('bulletins_paie', 1);

        $this->get('/admin/employes')
            ->assertOk()
            ->assertSee('Employé Fictif');

        $this->get('/admin/presences')->assertOk();
        $this->get('/admin/conges-employes/conge-employes')->assertOk();
        $this->get('/admin/periodes-paie/periode-paies')->assertOk();

        $this->get('/admin/employes/'.$employe->id.'/edit')
            ->assertOk()
            ->assertSee('Historique des bulletins de paie');

        $this->get('/admin/periodes-paie/periode-paies/'.$bulletin->periode_paie_id)
            ->assertOk();

        $this->get(route('personnel.bulletin', $bulletin->id))
            ->assertOk()
            ->assertSee('BULLETIN DE PAIE')
            ->assertSee('DEMO-RH-0001');

        $autreEmploye = Employe::query()->create([
            'compagnie_id' => $compagnie->id,
            'matricule' => 'EMP-TEST-0001',
            'nom' => 'Employé',
            'prenoms' => 'À conserver',
            'poste' => 'Comptable',
            'service' => 'comptabilite',
            'statut' => 'actif',
            'date_embauche' => today()->toDateString(),
        ]);

        (new SupprimerDonneesDemoSeeder)->run();

        $this->assertDatabaseMissing('employes', ['matricule' => 'DEMO-RH-0001']);
        $this->assertModelExists($autreEmploye);
        $this->assertDatabaseCount('bulletins_paie', 0);
        $this->assertDatabaseCount('periodes_paie', 0);
    }

    public function test_ne_cree_pas_de_donnees_de_demonstration_en_production(): void
    {
        $this->app->detectEnvironment(static fn (): string => 'production');

        (new DemoPersonnelSeeder)->run();

        $this->assertDatabaseCount('employes', 0);
        $this->assertDatabaseCount('bulletins_paie', 0);
    }

    public function test_le_super_admin_peut_afficher_le_formulaire_de_vente_au_guichet(): void
    {
        $administrateur = User::create([
            'prenom' => 'Admin',
            'nom' => 'Guichet',
            'telephone' => '70000002',
            'telephone_verifie' => true,
            'password' => 'motdepasse123',
        ]);
        $administrateur->assignerRolePourCompagnie('super_admin');

        $this->actingAs($administrateur)
            ->get('/admin/guichet/vente')
            ->assertOk()
            ->assertSee('Vente au guichet')
            ->assertSee('Montant reçu')
            ->assertSee('Remise');
    }
}
