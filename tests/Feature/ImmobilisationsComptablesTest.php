<?php

namespace Tests\Feature;

use App\Models\Compagnie;
use App\Models\CptAmortissement;
use App\Models\CptCategorieImmobilisation;
use App\Models\CptCompte;
use App\Models\User;
use App\Services\ImmobilisationsComptables;
use App\Services\Modules;
use DomainException;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ImmobilisationsComptablesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_asset_approval_generates_monthly_straight_line_schedule_with_the_configured_start_rule(): void
    {
        $this->travelTo('2026-03-31 12:00:00');
        $compagnie = Compagnie::factory()->create();
        $createur = $this->creerAdministrateur($compagnie, '70000011');
        $validateur = $this->creerAdministrateur($compagnie, '70000012');
        Modules::definirCompagnie($compagnie->id, 'comptabilite', true);
        $categorie = $this->creerCategorie($compagnie);
        $immobilisation = app(ImmobilisationsComptables::class)->creerImmobilisation($createur, $compagnie->id, [
            'categorie_id' => $categorie->id,
            'code' => 'BUS-001',
            'designation' => 'Autocar principal',
            'date_acquisition' => '2026-01-15',
            'date_mise_en_service' => '2026-01-15',
            'cout_acquisition' => 1200000,
            'valeur_residuelle' => 120000,
        ]);

        $this->assertSame('brouillon', $immobilisation->statut);

        try {
            app(ImmobilisationsComptables::class)->validerImmobilisation($createur, $compagnie->id, $immobilisation->id);
            self::fail('Le créateur ne doit pas valider sa propre immobilisation.');
        } catch (DomainException $exception) {
            $this->assertSame('L’immobilisation doit être validée par une personne différente de son créateur.', $exception->getMessage());
        }

        app(ImmobilisationsComptables::class)->validerImmobilisation($validateur, $compagnie->id, $immobilisation->id);

        $periodes = CptAmortissement::query()
            ->where('immobilisation_id', $immobilisation->id)
            ->orderBy('periode_debut')
            ->get();

        $this->assertSame('active', $immobilisation->fresh()->statut);
        $this->assertCount(13, $periodes);
        $this->assertSame('2026-01-15', $periodes->first()->periode_debut->toDateString());
        $this->assertSame('2026-01-31', $periodes->first()->periode_fin->toDateString());
        $this->assertSame(1080000, (int) $periodes->sum('montant'));
        $this->assertSame('planifie', $periodes->first()->statut);
    }

    public function test_depreciation_is_first_a_draft_then_only_a_different_user_can_post_it(): void
    {
        $this->travelTo('2026-03-31 12:00:00');
        $compagnie = Compagnie::factory()->create();
        $createur = $this->creerAdministrateur($compagnie, '70000021');
        $validateur = $this->creerAdministrateur($compagnie, '70000022');
        Modules::definirCompagnie($compagnie->id, 'comptabilite', true);
        $categorie = $this->creerCategorie($compagnie);
        $workflow = app(ImmobilisationsComptables::class);
        $immobilisation = $workflow->creerImmobilisation($createur, $compagnie->id, [
            'categorie_id' => $categorie->id,
            'code' => 'BUS-002',
            'designation' => 'Autocar secondaire',
            'date_acquisition' => '2026-01-15',
            'date_mise_en_service' => '2026-01-15',
            'cout_acquisition' => 1200000,
            'valeur_residuelle' => 120000,
        ]);
        $workflow->validerImmobilisation($validateur, $compagnie->id, $immobilisation->id);
        $periode = $immobilisation->amortissements()->orderBy('periode_fin')->firstOrFail();

        $proposition = $workflow->proposerEcriture($createur, $compagnie->id, $periode->id);

        $this->assertSame('brouillon', $proposition->statut);
        $this->assertSame('brouillon', $proposition->ecriture->statut);
        $this->assertSame($periode->montant, (int) $proposition->ecriture->lignes->sum('debit'));
        $this->assertSame($periode->montant, (int) $proposition->ecriture->lignes->sum('credit'));

        try {
            $workflow->comptabiliserAmortissement($createur, $compagnie->id, $periode->id);
            self::fail('Le préparateur ne doit pas comptabiliser sa propre proposition.');
        } catch (DomainException $exception) {
            $this->assertSame('La proposition d’amortissement doit être comptabilisée par une personne différente de son préparateur.', $exception->getMessage());
        }

        $workflow->comptabiliserAmortissement($validateur, $compagnie->id, $periode->id);

        $this->assertSame('comptabilise', $periode->fresh()->statut);
        $this->assertSame('comptabilise', $proposition->ecriture->fresh()->statut);
        $this->assertDatabaseHas('cpt_lignes', [
            'ecriture_id' => $proposition->ecriture_id,
            'compte_id' => $categorie->compte_charge_id,
            'debit' => $periode->montant,
            'credit' => 0,
        ]);
        $this->assertDatabaseHas('cpt_lignes', [
            'ecriture_id' => $proposition->ecriture_id,
            'compte_id' => $categorie->compte_amortissement_id,
            'debit' => 0,
            'credit' => $periode->montant,
        ]);
    }

    public function test_administrator_cannot_view_assets_from_another_company(): void
    {
        $compagnieA = Compagnie::factory()->create(['nom' => 'Compagnie A']);
        $compagnieB = Compagnie::factory()->create(['nom' => 'Compagnie B']);
        $administrateurA = $this->creerAdministrateur($compagnieA, '70000031');
        $administrateurB = $this->creerAdministrateur($compagnieB, '70000032');
        Modules::definirCompagnie($compagnieA->id, 'comptabilite', true);
        Modules::definirCompagnie($compagnieB->id, 'comptabilite', true);
        $categorie = $this->creerCategorie($compagnieB);
        app(ImmobilisationsComptables::class)->creerImmobilisation($administrateurB, $compagnieB->id, [
            'categorie_id' => $categorie->id,
            'code' => 'CONFIDENTIEL-001',
            'designation' => 'Autocar confidentiel',
            'date_acquisition' => '2026-01-15',
            'date_mise_en_service' => '2026-01-15',
            'cout_acquisition' => 1200000,
            'valeur_residuelle' => 120000,
        ]);

        $this->actingAs($administrateurA)
            ->get('/admin/immobilisations-comptables')
            ->assertOk()
            ->assertSee('Immobilisations')
            ->assertDontSee('Autocar confidentiel');
    }

    public function test_category_cannot_use_an_account_from_another_company(): void
    {
        $compagnie = Compagnie::factory()->create();
        $autreCompagnie = Compagnie::factory()->create();
        $administrateur = $this->creerAdministrateur($compagnie, '70000041');
        $comptes = $this->creerComptes($compagnie);
        $compteExterne = $this->creerCompte($autreCompagnie->id, '2451');
        Modules::definirCompagnie($compagnie->id, 'comptabilite', true);

        try {
            app(ImmobilisationsComptables::class)->creerCategorie($administrateur, $compagnie->id, [
                'code' => 'TRANSPORT',
                'libelle' => 'Matériel de transport',
                'duree_utilite_mois' => 60,
                'mode_amortissement' => 'lineaire',
                'premiere_periode' => 'prorata_temporis',
                'compte_actif_id' => $compteExterne->id,
                'compte_amortissement_id' => $comptes['amortissement']->id,
                'compte_charge_id' => $comptes['charge']->id,
            ]);
            self::fail('Un compte d’une autre compagnie ne doit pas être accepté.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('compte_actif_id', $exception->errors());
        }

        $this->assertDatabaseMissing('cpt_categories_immobilisations', [
            'compagnie_id' => $compagnie->id,
            'code' => 'TRANSPORT',
        ]);
    }

    private function creerCategorie(Compagnie $compagnie): CptCategorieImmobilisation
    {
        $administrateur = $this->creerAdministrateur($compagnie, '70000051');
        Modules::definirCompagnie($compagnie->id, 'comptabilite', true);
        $comptes = $this->creerComptes($compagnie);

        return app(ImmobilisationsComptables::class)->creerCategorie($administrateur, $compagnie->id, [
            'code' => 'TRANSPORT',
            'libelle' => 'Matériel de transport',
            'duree_utilite_mois' => 12,
            'mode_amortissement' => 'lineaire',
            'premiere_periode' => 'prorata_temporis',
            'compte_actif_id' => $comptes['actif']->id,
            'compte_amortissement_id' => $comptes['amortissement']->id,
            'compte_charge_id' => $comptes['charge']->id,
        ]);
    }

    /**
     * @return array{actif: CptCompte, amortissement: CptCompte, charge: CptCompte}
     */
    private function creerComptes(Compagnie $compagnie): array
    {
        return [
            'actif' => $this->creerCompte($compagnie->id, '2451'),
            'amortissement' => $this->creerCompte($compagnie->id, '2845'),
            'charge' => $this->creerCompte($compagnie->id, '681'),
        ];
    }

    private function creerCompte(int $compagnieId, string $numero): CptCompte
    {
        return CptCompte::query()->create([
            'compagnie_id' => $compagnieId,
            'numero' => $numero,
            'intitule' => 'Compte '.$numero,
            'classe' => (int) substr($numero, 0, 1),
            'actif' => true,
        ]);
    }

    private function creerAdministrateur(Compagnie $compagnie, string $telephone): User
    {
        $administrateur = User::query()->create([
            'prenom' => 'Admin',
            'nom' => 'Comptable',
            'telephone' => $telephone,
            'telephone_verifie' => true,
            'actif' => true,
            'password' => 'motdepasse123',
        ]);
        $administrateur->assignerRolePourCompagnie('admin_compagnie', $compagnie->id);

        return $administrateur;
    }
}
