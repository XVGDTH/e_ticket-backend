<?php

namespace Tests\Feature;

use App\Filament\Pages\Login;
use App\Models\Compagnie;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SecuriteCompteTest extends TestCase
{
    use RefreshDatabase;

    private Compagnie $compagnie;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->compagnie = Compagnie::create(['nom' => 'CompagnieTest', 'slug' => 'compagnie-test', 'actif' => true]);
    }

    private function admin(string $telephone, array $extra = []): User
    {
        $user = User::create(array_merge([
            'prenom' => 'Admin',
            'nom' => 'Test',
            'telephone' => $telephone,
            'telephone_verifie' => true,
            'password' => 'motdepasse123',
        ], $extra));

        $user->assignerRolePourCompagnie('admin_compagnie', $this->compagnie->id);

        return $user;
    }

    public function test_un_mot_de_passe_temporaire_force_la_page_de_profil(): void
    {
        $user = $this->admin('75000001', ['doit_changer_mot_de_passe' => true]);

        $this->actingAs($user)->get('/admin')->assertRedirect(route('filament.admin.auth.profile'));
        $this->actingAs($user)->get(route('filament.admin.auth.profile'))->assertOk();
    }

    public function test_sans_obligation_le_panel_reste_accessible(): void
    {
        $user = $this->admin('75000002');

        $this->actingAs($user)->get('/admin')->assertOk();
    }

    public function test_la_double_authentification_est_exigee_aux_admins_quand_elle_est_activee(): void
    {
        config(['securite.mfa_obligatoire_admins' => true]);

        $user = $this->admin('75000003');

        $this->actingAs($user)->get('/admin')->assertRedirect(route('filament.admin.auth.profile'));

        $user->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();

        $this->actingAs($user->fresh())->get('/admin')->assertOk();
    }

    public function test_connexion_par_telephone(): void
    {
        $user = $this->admin('75000004');

        Livewire::test(Login::class)
            ->fillForm(['telephone' => '75000004', 'password' => 'motdepasse123'])
            ->call('authenticate')
            ->assertHasNoErrors()
            ->assertRedirect();

        $this->assertAuthenticatedAs($user);
    }

    public function test_mauvais_mot_de_passe_refuse(): void
    {
        $this->admin('75000005');

        Livewire::test(Login::class)
            ->fillForm(['telephone' => '75000005', 'password' => 'mauvais-mot-de-passe'])
            ->call('authenticate')
            ->assertHasErrors(['data.telephone']);

        $this->assertGuest();
    }
}