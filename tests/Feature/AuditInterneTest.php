<?php

namespace Tests\Feature;

use App\Filament\Pages\AuditInterne;
use App\Models\Compagnie;
use App\Models\ControleAudit;
use App\Models\RecommandationAudit;
use App\Models\User;
use App\Models\Ville;
use App\Services\Modules;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AuditInterneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_admin_ne_voit_que_les_donnees_daudit_de_sa_compagnie(): void
    {
        $compagnieA = $this->creerCompagnie('CompagnieAuditA', 'audit-a');
        $compagnieB = $this->creerCompagnie('CompagnieAuditB', 'audit-b');
        $administrateur = $this->creerAdministrateur($compagnieA);
        Modules::definirCompagnie($compagnieA->id, 'audit_interne', true);

        ControleAudit::factory()->for($compagnieA, 'compagnie')->create([
            'titre' => 'Contrôle de la compagnie A',
            'objectif' => 'Vérifier le rapprochement des ventes.',
            'frequence' => 'mensuelle',
            'niveau_risque' => 'moyen',
        ]);
        ControleAudit::factory()->for($compagnieB, 'compagnie')->create([
            'titre' => 'Contrôle confidentiel de la compagnie B',
            'objectif' => 'Ne doit pas être visible par A.',
            'frequence' => 'mensuelle',
            'niveau_risque' => 'moyen',
        ]);

        $this->actingAs($administrateur)
            ->get('/admin/controle-interne')
            ->assertOk()
            ->assertSee('Contrôle de la compagnie A')
            ->assertDontSee('Contrôle confidentiel de la compagnie B');
    }

    public function test_admin_ne_peut_pas_ouvrir_le_module_si_sa_compagnie_ne_lactive_pas(): void
    {
        $compagnie = $this->creerCompagnie('CompagnieAudit', 'audit');
        $administrateur = $this->creerAdministrateur($compagnie);
        Modules::definirCompagnie($compagnie->id, 'audit_interne', false);

        $this->actingAs($administrateur)
            ->get('/admin/controle-interne')
            ->assertForbidden();
    }

    public function test_admin_limite_a_une_ville_ne_peut_pas_voir_le_plan_complet_de_sa_compagnie(): void
    {
        $compagnie = $this->creerCompagnie('CompagnieAudit', 'audit');
        $ville = Ville::create(['nom' => 'VilleAudit']);
        $administrateur = $this->creerAdministrateur($compagnie, $ville->id);
        Modules::definirCompagnie($compagnie->id, 'audit_interne', true);

        $this->actingAs($administrateur)
            ->get('/admin/controle-interne')
            ->assertForbidden();
    }

    public function test_admin_peut_ajouter_un_controle_avec_un_responsable_de_sa_compagnie(): void
    {
        $compagnie = $this->creerCompagnie('CompagnieAudit', 'audit');
        $administrateur = $this->creerAdministrateur($compagnie);
        Modules::definirCompagnie($compagnie->id, 'audit_interne', true);

        $this->actingAs($administrateur);

        Livewire::test(AuditInterne::class)
            ->callAction('creerControle', [
                'titre' => 'Vérifier les clôtures de caisse',
                'objectif' => 'Confirmer que les clôtures sont validées chaque jour.',
                'procedure' => 'Comparer les totaux de caisse et les reçus.',
                'frequence' => 'quotidienne',
                'niveau_risque' => 'eleve',
                'responsable_id' => $administrateur->id,
                'prochaine_echeance' => '2026-12-01',
            ]);

        $this->assertDatabaseHas('ci_controles', [
            'compagnie_id' => $compagnie->id,
            'titre' => 'Vérifier les clôtures de caisse',
            'responsable_id' => $administrateur->id,
            'cree_par' => $administrateur->id,
        ]);
    }

    public function test_admin_peut_suivre_une_recommandation_jusqua_sa_resolution(): void
    {
        $compagnie = $this->creerCompagnie('CompagnieAudit', 'audit');
        $administrateur = $this->creerAdministrateur($compagnie);
        Modules::definirCompagnie($compagnie->id, 'audit_interne', true);

        $recommandation = RecommandationAudit::factory()->for($compagnie, 'compagnie')->create([
            'titre' => 'Documenter les annulations',
            'constat' => 'Les annulations ne sont pas toutes justifiées.',
            'action_requise' => 'Joindre une justification à chaque annulation.',
            'priorite' => 'haute',
            'statut' => 'ouverte',
        ]);

        $this->actingAs($administrateur);

        Livewire::test(AuditInterne::class)
            ->callAction('modifierRecommandation', [
                'controle_id' => null,
                'titre' => 'Documenter les annulations',
                'constat' => 'Les annulations ne sont pas toutes justifiées.',
                'action_requise' => 'Joindre une justification à chaque annulation.',
                'priorite' => 'haute',
                'statut' => 'terminee',
                'responsable_id' => $administrateur->id,
                'echeance' => '2026-12-01',
                'resolution' => 'La justification est maintenant obligatoire.',
            ], ['id' => $recommandation->id]);

        $recommandation->refresh();

        $this->assertSame('terminee', $recommandation->statut);
        $this->assertSame('La justification est maintenant obligatoire.', $recommandation->resolution);
        $this->assertNotNull($recommandation->terminee_le);
    }

    public function test_admin_doit_renseigner_une_resolution_pour_terminer_une_recommandation(): void
    {
        $compagnie = $this->creerCompagnie('CompagnieAudit', 'audit');
        $administrateur = $this->creerAdministrateur($compagnie);
        Modules::definirCompagnie($compagnie->id, 'audit_interne', true);
        $recommandation = RecommandationAudit::factory()->for($compagnie, 'compagnie')->create();

        $this->actingAs($administrateur);

        Livewire::test(AuditInterne::class)
            ->callAction('modifierRecommandation', [
                'controle_id' => null,
                'titre' => $recommandation->titre,
                'constat' => $recommandation->constat,
                'action_requise' => $recommandation->action_requise,
                'priorite' => $recommandation->priorite,
                'statut' => 'terminee',
                'responsable_id' => null,
                'echeance' => null,
                'resolution' => null,
            ], ['id' => $recommandation->id])
            ->assertHasActionErrors(['resolution']);

        $this->assertDatabaseHas('ci_recommandations', [
            'id' => $recommandation->id,
            'statut' => 'ouverte',
            'terminee_le' => null,
        ]);
    }

    public function test_admin_ne_peut_pas_lier_une_recommandation_a_un_controle_dune_autre_compagnie(): void
    {
        $compagnieA = $this->creerCompagnie('CompagnieAuditA', 'audit-a');
        $compagnieB = $this->creerCompagnie('CompagnieAuditB', 'audit-b');
        $administrateur = $this->creerAdministrateur($compagnieA);
        Modules::definirCompagnie($compagnieA->id, 'audit_interne', true);
        $controleExterne = ControleAudit::factory()->for($compagnieB, 'compagnie')->create();

        $this->actingAs($administrateur);

        Livewire::test(AuditInterne::class)
            ->callAction('creerRecommandation', [
                'controle_id' => $controleExterne->id,
                'titre' => 'Recommandation hors périmètre',
                'constat' => 'Constat de contrôle.',
                'action_requise' => 'Corriger le point relevé.',
                'priorite' => 'normale',
                'responsable_id' => null,
                'echeance' => null,
            ])
            ->assertHasActionErrors(['controle_id']);

        $this->assertDatabaseMissing('ci_recommandations', [
            'compagnie_id' => $compagnieA->id,
            'titre' => 'Recommandation hors périmètre',
        ]);
    }

    private function creerCompagnie(string $nom, string $slug): Compagnie
    {
        return Compagnie::factory()->create([
            'nom' => $nom,
            'slug' => $slug,
        ]);
    }

    private function creerAdministrateur(Compagnie $compagnie, ?int $villeId = null): User
    {
        $administrateur = User::create([
            'prenom' => 'Admin',
            'nom' => 'Audit',
            'telephone' => '70000001',
            'telephone_verifie' => true,
            'actif' => true,
            'password' => 'motdepasse123',
        ]);
        $administrateur->assignerRolePourCompagnie('admin_compagnie', $compagnie->id, null, $villeId);

        return $administrateur;
    }
}
