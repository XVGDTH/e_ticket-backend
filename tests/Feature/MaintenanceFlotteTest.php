<?php

namespace Tests\Feature;

use App\Models\Compagnie;
use App\Models\User;
use App\Services\Modules;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceFlotteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Modules::oublier();
        config(['securite.mfa_obligatoire_admins' => false]);
    }

    public function test_le_super_admin_peut_ouvrir_le_tableau_de_maintenance(): void
    {
        Compagnie::factory()->create();
        $administrateur = $this->creerUtilisateur('super_admin');

        $this->actingAs($administrateur)
            ->get('/admin/maintenance-et-carburant')
            ->assertOk()
            ->assertSee('Maintenance et carburant')
            ->assertSee('/admin/maintenance-et-carburant')
            ->assertSee('Cars à surveiller')
            ->assertSee('Dernières dépenses');
    }

    public function test_un_admin_de_compagnie_ne_peut_ouvrir_le_module_que_s_il_est_active(): void
    {
        $compagnie = Compagnie::factory()->create();
        $administrateur = $this->creerUtilisateur('admin_compagnie', (int) $compagnie->id);

        $this->actingAs($administrateur)
            ->get('/admin/maintenance-et-carburant')
            ->assertForbidden();

        Modules::definirCompagnie((int) $compagnie->id, 'maintenance', true);

        $this->get('/admin/maintenance-et-carburant')
            ->assertOk()
            ->assertSee('Maintenance et carburant');
    }

    private function creerUtilisateur(string $role, ?int $compagnieId = null): User
    {
        $utilisateur = User::create([
            'prenom' => 'Admin',
            'nom' => 'Maintenance',
            'telephone' => fake()->unique()->numerify('700#####'),
            'telephone_verifie' => true,
            'actif' => true,
            'password' => 'motdepasse123',
        ]);
        $utilisateur->assignerRolePourCompagnie($role, $compagnieId);

        return $utilisateur;
    }
}
