<?php

namespace Tests\Feature;

use App\Models\Compagnie;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ControleGestionPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        config(['securite.mfa_obligatoire_admins' => false]);
    }

    public function test_les_pages_de_controle_de_gestion_sont_rendues_sans_partial_manquant(): void
    {
        Compagnie::factory()->create();
        $administrateur = User::create([
            'prenom' => 'Admin',
            'nom' => 'Gestion',
            'telephone' => '70000005',
            'telephone_verifie' => true,
            'actif' => true,
            'password' => 'motdepasse123',
        ]);
        $administrateur->assignerRolePourCompagnie('super_admin');

        $this->actingAs($administrateur)
            ->get('/admin/budget')
            ->assertOk()
            ->assertSee('Aucun budget');

        $this->get('/admin/ecarts')
            ->assertOk()
            ->assertSee('Aucun budget validé');

        $this->get('/admin/reporting-mensuel')
            ->assertOk()
            ->assertSee('Aucun voyage de car sur ce mois.');
    }
}
