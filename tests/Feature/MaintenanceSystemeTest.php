<?php

namespace Tests\Feature;

use App\Filament\Pages\MaintenanceSysteme;
use App\Models\Compagnie;
use App\Models\User;
use App\Services\Maintenance\OperationsMaintenance;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class MaintenanceSystemeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_un_admin_de_compagnie_ne_peut_pas_ouvrir_le_centre_de_maintenance(): void
    {
        $compagnie = Compagnie::factory()->create();
        $administrateur = $this->creerUtilisateur('admin_compagnie', $compagnie->id);

        $this->actingAs($administrateur)
            ->get('/admin/maintenance-systeme')
            ->assertForbidden();
    }

    public function test_le_super_admin_peut_ouvrir_le_centre_de_maintenance(): void
    {
        config(['securite.mfa_obligatoire_admins' => false]);
        $superAdministrateur = $this->creerUtilisateur('super_admin');
        $this->assertTrue($superAdministrateur->isSuperAdmin());
        $this->assertTrue($superAdministrateur->canAccessPanel(Filament::getPanel('admin')));

        $this->actingAs($superAdministrateur)
            ->get('/admin/maintenance-systeme')
            ->assertOk()
            ->assertSee('Maintenance et santé de la plateforme')
            ->assertSee('Contrôles de santé')
            ->assertSee('Outil de sauvegarde');
    }

    public function test_un_admin_de_compagnie_ne_peut_pas_lancer_une_operation_de_maintenance(): void
    {
        $compagnie = Compagnie::factory()->create();
        $administrateur = $this->creerUtilisateur('admin_compagnie', $compagnie->id);

        $this->actingAs($administrateur);
        Artisan::shouldReceive('call')->never();

        $this->expectException(HttpException::class);

        (new MaintenanceSysteme)->nettoyerCaches();
    }

    public function test_super_admin_peut_relancer_un_travail_echoue_par_uuid(): void
    {
        $superAdministrateur = $this->creerUtilisateur('super_admin');
        $uuid = (string) Str::uuid();
        DB::table('failed_jobs')->insert([
            'uuid' => $uuid,
            'connection' => 'database',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => "RuntimeException: password=top-secret\ntrace",
            'failed_at' => now(),
        ]);
        Artisan::shouldReceive('call')
            ->once()
            ->with('queue:retry', [$uuid])
            ->andReturn(0);

        $this->actingAs($superAdministrateur);

        (new MaintenanceSysteme)->relancerTravail($uuid);
    }

    public function test_migrations_sont_bloquees_si_la_sauvegarde_prealable_echoue(): void
    {
        $superAdministrateur = $this->creerUtilisateur('super_admin');
        $this->mock(OperationsMaintenance::class, function (MockInterface $mock): void {
            $mock->shouldReceive('migrationsEnAttente')
                ->once()
                ->andReturn(['2026_11_06_120000_exemple']);
            $mock->shouldReceive('sauvegarderBase')
                ->once()
                ->andReturn(['ok' => false, 'message' => 'Client mysqldump absent.']);
        });
        Artisan::shouldReceive('call')->never();

        $this->actingAs($superAdministrateur);

        (new MaintenanceSysteme)->executerMigrations();
    }

    public function test_les_commandes_non_autorisees_sont_refusees(): void
    {
        Artisan::shouldReceive('call')->never();

        $resultat = app(OperationsMaintenance::class)->executerCommande('tinker', [], 'Commande libre');

        $this->assertFalse($resultat['ok']);
    }

    public function test_le_diagnostic_signale_un_client_de_sauvegarde_absent(): void
    {
        config(['maintenance.backup_binary' => 'outil-inexistant-123']);

        $this->assertFalse(app(OperationsMaintenance::class)->clientSauvegardeDisponible());
    }

    public function test_l_activation_du_mode_maintenance_cree_un_lien_de_contournement_prive(): void
    {
        $superAdministrateur = $this->creerUtilisateur('super_admin');
        Artisan::shouldReceive('call')
            ->once()
            ->with('down', \Mockery::on(fn (array $arguments): bool => preg_match('/^[A-Za-z0-9]{48}$/', $arguments['--secret'] ?? '') === 1
                && ($arguments['--retry'] ?? null) === 60
                && ($arguments['--no-interaction'] ?? null) === true))
            ->andReturn(0);

        $this->actingAs($superAdministrateur);
        $page = new MaintenanceSysteme;

        $page->basculerModeMaintenance(true);

        $this->assertMatchesRegularExpression(
            '#^http://localhost:8000/[A-Za-z0-9]{48}/admin/maintenance-systeme$#',
            $page->maintenanceBypassUrl,
        );
    }

    public function test_journal_affiche_les_lignes_recentes_apres_masquage_des_secrets(): void
    {
        $chemin = storage_path('logs/laravel-maintenance-test-'.Str::uuid().'.log');
        file_put_contents($chemin, "password=top-secret Bearer abc123token\n");

        try {
            $lignes = app(OperationsMaintenance::class)->journauxRecents();
            $contenu = implode("\n", array_column($lignes, 'message'));

            $this->assertStringContainsString('[masqué]', $contenu);
            $this->assertStringNotContainsString('top-secret', $contenu);
            $this->assertStringNotContainsString('abc123token', $contenu);
        } finally {
            if (is_file($chemin)) {
                unlink($chemin);
            }
        }
    }

    public function test_le_super_admin_peut_purger_les_caches_via_la_commande_autorisee(): void
    {
        $superAdministrateur = $this->creerUtilisateur('super_admin');
        foreach ([
            'config:clear',
            'clear-compiled',
            'event:clear',
            'route:clear',
            'view:clear',
            'filament:clear-cached-components',
            'icons:clear',
        ] as $commande) {
            Artisan::shouldReceive('call')
                ->once()
                ->with($commande, [])
                ->andReturn(0);
        }

        $this->actingAs($superAdministrateur);

        (new MaintenanceSysteme)->nettoyerCaches();
    }

    public function test_le_super_admin_peut_signaler_le_redemarrage_des_workers(): void
    {
        $superAdministrateur = $this->creerUtilisateur('super_admin');
        Artisan::shouldReceive('call')
            ->once()
            ->with('queue:restart', [])
            ->andReturn(0);

        $this->actingAs($superAdministrateur);

        (new MaintenanceSysteme)->redemarrerWorkers();
    }

    private function creerUtilisateur(string $role, ?int $compagnieId = null): User
    {
        $utilisateur = User::create([
            'prenom' => 'Super',
            'nom' => 'Administrateur',
            'telephone' => fake()->unique()->numerify('700#####'),
            'telephone_verifie' => true,
            'actif' => true,
            'password' => 'motdepasse123',
        ]);
        $utilisateur->assignerRolePourCompagnie($role, $compagnieId);

        return $utilisateur;
    }
}
