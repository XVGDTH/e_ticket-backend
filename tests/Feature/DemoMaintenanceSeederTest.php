<?php

namespace Tests\Feature;

use App\Models\Compagnie;
use App\Models\User;
use App\Models\Vehicule;
use Database\Seeders\DemoMaintenanceSeeder;
use Database\Seeders\SupprimerDonneesDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoMaintenanceSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_cree_des_donnees_de_maintenance_fictives_et_ne_les_duplique_pas(): void
    {
        $compagnie = Compagnie::factory()->create();
        $administrateur = User::create([
            'prenom' => 'Admin',
            'nom' => 'Démo',
            'telephone' => '70000004',
            'telephone_verifie' => true,
            'actif' => true,
            'password' => 'motdepasse123',
        ]);
        $administrateur->assignerRolePourCompagnie('super_admin');

        (new DemoMaintenanceSeeder)->run();
        (new DemoMaintenanceSeeder)->run();

        $this->assertDatabaseCount('vehicules', 2);
        $this->assertDatabaseCount('depenses_vehicules', 3);
        $this->assertDatabaseHas('vehicules', [
            'compagnie_id' => $compagnie->id,
            'immatriculation' => 'DEMO-MNT-001',
            'statut' => 'maintenance',
        ]);
        $this->assertDatabaseHas('depenses_vehicules', [
            'compagnie_id' => $compagnie->id,
            'reference' => 'DEMO-MNT-CARBURANT-001',
            'montant' => 42000,
        ]);

        $this->actingAs($administrateur)
            ->get('/admin/maintenance-et-carburant')
            ->assertOk()
            ->assertSee('Dernières dépenses')
            ->assertSee('Station Démo')
            ->assertSee('Garage Démo')
            ->assertSee('95 000')
            ->assertSee('42 000');

        $vehiculeConserve = Vehicule::query()->create([
            'compagnie_id' => $compagnie->id,
            'immatriculation' => 'CONSERVE-001',
            'places' => 20,
            'km_actuel' => 1000,
            'statut' => 'actif',
        ]);

        (new SupprimerDonneesDemoSeeder)->run();

        $this->assertDatabaseMissing('vehicules', ['immatriculation' => 'DEMO-MNT-001']);
        $this->assertDatabaseMissing('depenses_vehicules', ['reference' => 'DEMO-MNT-CARBURANT-001']);
        $this->assertModelExists($vehiculeConserve);
    }

    public function test_ne_cree_pas_de_donnees_fictives_en_production(): void
    {
        $this->app->detectEnvironment(static fn (): string => 'production');

        (new DemoMaintenanceSeeder)->run();

        $this->assertDatabaseCount('vehicules', 0);
        $this->assertDatabaseCount('depenses_vehicules', 0);
    }
}
