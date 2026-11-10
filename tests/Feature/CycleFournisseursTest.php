<?php

namespace Tests\Feature;

use App\Filament\Pages\CycleFournisseurs as CycleFournisseursPage;
use App\Models\Compagnie;
use App\Models\FactureFournisseur;
use App\Models\Fournisseur;
use App\Models\ReceptionFournisseur;
use App\Models\User;
use App\Services\AuditInterne\CycleFournisseurs;
use App\Services\Modules;
use DomainException;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class CycleFournisseursTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_admin_peut_ouvrir_la_page_et_creer_un_fournisseur_depuis_linterface(): void
    {
        $compagnie = $this->creerCompagnie();
        $administrateur = $this->creerAdministrateur($compagnie, '70000000');

        $this->actingAs($administrateur);
        $this->get('/admin/cycle-fournisseurs')
            ->assertOk()
            ->assertSee('Cycle fournisseur maîtrisé');

        Livewire::test(CycleFournisseursPage::class)
            ->callAction('creerFournisseur', [
                'nom' => 'Fournisseur créé depuis Filament',
                'identifiant_fiscal' => 'ID-TEST',
                'telephone' => '70000099',
                'email' => 'fournisseur@example.test',
                'adresse' => 'Dakar',
            ]);

        $this->assertDatabaseHas('ci_fournisseurs', [
            'compagnie_id' => $compagnie->id,
            'nom' => 'Fournisseur créé depuis Filament',
            'cree_par' => $administrateur->id,
        ]);
    }

    public function test_reception_met_a_jour_le_stock_seulement_apres_validation_par_un_autre_acteur(): void
    {
        $compagnie = $this->creerCompagnie();
        $createur = $this->creerAdministrateur($compagnie, '70000001');
        $validateur = $this->creerAdministrateur($compagnie, '70000002');
        $workflow = app(CycleFournisseurs::class);
        $fournisseur = $workflow->creerFournisseur($createur, $compagnie->id, [
            'nom' => 'Fournisseur Test',
        ]);
        $article = $workflow->creerArticleStock($createur, $compagnie->id, [
            'nom' => 'Carton',
            'reference' => 'ART-001',
            'unite' => 'pièce',
        ]);
        $reception = $workflow->creerReception($createur, $compagnie->id, [
            'fournisseur_id' => $fournisseur->id,
            'recu_le' => '2026-11-06',
            'lignes' => [
                ['article_id' => $article->id, 'quantite' => 2.5],
            ],
        ]);
        $receptionSuivante = $workflow->creerReception($createur, $compagnie->id, [
            'fournisseur_id' => $fournisseur->id,
            'recu_le' => '2026-11-06',
            'lignes' => [
                ['article_id' => $article->id, 'quantite' => 1],
            ],
        ]);

        $this->assertSame('REC-2026-000001', $reception->numero);
        $this->assertSame('REC-2026-000002', $receptionSuivante->numero);
        $this->assertSame('0.000', $article->fresh()->quantite_stock);

        try {
            $workflow->validerReception($createur, $compagnie->id, $reception->id);
            self::fail('Le créateur ne doit pas pouvoir valider sa propre réception.');
        } catch (DomainException $exception) {
            $this->assertSame('La réception doit être validée par une personne différente de son créateur.', $exception->getMessage());
        }

        $this->assertSame('0.000', $article->fresh()->quantite_stock);

        $workflow->validerReception($validateur, $compagnie->id, $reception->id);

        $this->assertSame('2.500', $article->fresh()->quantite_stock);
        $this->assertDatabaseHas('ci_mouvements_stock', [
            'article_id' => $article->id,
            'reception_id' => $reception->id,
            'type' => 'entree',
            'quantite' => '2.500',
            'cree_par' => $validateur->id,
        ]);
    }

    public function test_le_createur_du_fournisseur_ne_peut_pas_saisir_son_paiement(): void
    {
        $compagnie = $this->creerCompagnie();
        $createurFournisseur = $this->creerAdministrateur($compagnie, '70000003');
        $createurReception = $this->creerAdministrateur($compagnie, '70000004');
        $validateurReception = $this->creerAdministrateur($compagnie, '70000005');
        $createurFacture = $this->creerAdministrateur($compagnie, '70000006');
        $validateurFacture = $this->creerAdministrateur($compagnie, '70000007');
        $createurPaiement = $this->creerAdministrateur($compagnie, '70000008');
        $validateurPaiement = $this->creerAdministrateur($compagnie, '70000009');
        $workflow = app(CycleFournisseurs::class);

        $fournisseur = $workflow->creerFournisseur($createurFournisseur, $compagnie->id, [
            'nom' => 'Fournisseur Paiement',
        ]);
        $article = $workflow->creerArticleStock($createurFournisseur, $compagnie->id, [
            'nom' => 'Papier',
            'reference' => 'ART-002',
            'unite' => 'ramette',
        ]);
        $reception = $workflow->creerReception($createurReception, $compagnie->id, [
            'fournisseur_id' => $fournisseur->id,
            'recu_le' => '2026-11-06',
            'lignes' => [
                ['article_id' => $article->id, 'quantite' => 1],
            ],
        ]);
        $workflow->validerReception($validateurReception, $compagnie->id, $reception->id);

        try {
            $workflow->creerFacture($createurReception, $compagnie->id, [
                'reception_id' => $reception->id,
                'reference_fournisseur' => 'FAC-EXT-099',
                'date_facture' => '2026-11-06',
                'montant' => 10000,
            ]);
            self::fail('Le créateur de la réception ne doit pas créer la facture associée.');
        } catch (DomainException $exception) {
            $this->assertSame('La réception et la facture doivent être traitées par des personnes différentes.', $exception->getMessage());
        }

        $facture = $workflow->creerFacture($createurFacture, $compagnie->id, [
            'reception_id' => $reception->id,
            'reference_fournisseur' => 'FAC-EXT-100',
            'date_facture' => '2026-11-06',
            'montant' => 10000,
        ]);
        $workflow->validerFacture($validateurFacture, $compagnie->id, $facture->id);

        try {
            $workflow->creerPaiement($createurFournisseur, $compagnie->id, [
                'facture_id' => $facture->id,
                'montant' => 10000,
                'paye_le' => '2026-11-06',
                'mode' => 'virement',
            ]);
            self::fail('Le créateur du fournisseur ne doit pas pouvoir saisir un paiement à son profit.');
        } catch (DomainException $exception) {
            $this->assertSame('La création du fournisseur et le paiement doivent être effectués par des personnes différentes.', $exception->getMessage());
        }

        $paiement = $workflow->creerPaiement($createurPaiement, $compagnie->id, [
            'facture_id' => $facture->id,
            'montant' => 10000,
            'paye_le' => '2026-11-06',
            'mode' => 'virement',
        ]);

        try {
            $workflow->validerPaiement($createurPaiement, $compagnie->id, $paiement->id);
            self::fail('Le créateur du paiement ne doit pas pouvoir le valider.');
        } catch (DomainException $exception) {
            $this->assertSame('Le paiement doit être validé par une personne distincte des intervenants précédents.', $exception->getMessage());
        }

        $workflow->validerPaiement($validateurPaiement, $compagnie->id, $paiement->id);

        $this->assertDatabaseHas('ci_paiements_fournisseurs', [
            'id' => $paiement->id,
            'statut' => 'valide',
            'cree_par' => $createurPaiement->id,
            'valide_par' => $validateurPaiement->id,
        ]);
        $this->assertDatabaseHas('ci_factures_fournisseurs', [
            'id' => $facture->id,
            'statut' => 'reglee',
        ]);
    }

    public function test_un_deuxieme_paiement_en_attente_ne_peut_pas_depasser_le_solde(): void
    {
        $compagnie = $this->creerCompagnie();
        $createurFournisseur = $this->creerAdministrateur($compagnie, '70000010');
        $createurReception = $this->creerAdministrateur($compagnie, '70000011');
        $validateurReception = $this->creerAdministrateur($compagnie, '70000012');
        $createurFacture = $this->creerAdministrateur($compagnie, '70000013');
        $validateurFacture = $this->creerAdministrateur($compagnie, '70000014');
        $createurPaiement = $this->creerAdministrateur($compagnie, '70000015');
        $workflow = app(CycleFournisseurs::class);

        $fournisseur = $workflow->creerFournisseur($createurFournisseur, $compagnie->id, ['nom' => 'Fournisseur Solde']);
        $article = $workflow->creerArticleStock($createurFournisseur, $compagnie->id, [
            'nom' => 'Enveloppe',
            'reference' => 'ART-003',
            'unite' => 'unité',
        ]);
        $reception = $workflow->creerReception($createurReception, $compagnie->id, [
            'fournisseur_id' => $fournisseur->id,
            'recu_le' => '2026-11-06',
            'lignes' => [
                ['article_id' => $article->id, 'quantite' => 1],
            ],
        ]);
        $workflow->validerReception($validateurReception, $compagnie->id, $reception->id);
        $facture = $workflow->creerFacture($createurFacture, $compagnie->id, [
            'reception_id' => $reception->id,
            'reference_fournisseur' => 'FAC-EXT-200',
            'date_facture' => '2026-11-06',
            'montant' => 10000,
        ]);
        $workflow->validerFacture($validateurFacture, $compagnie->id, $facture->id);
        $workflow->creerPaiement($createurPaiement, $compagnie->id, [
            'facture_id' => $facture->id,
            'montant' => 8000,
            'paye_le' => '2026-11-06',
            'mode' => 'virement',
        ]);

        try {
            $workflow->creerPaiement($createurPaiement, $compagnie->id, [
                'facture_id' => $facture->id,
                'montant' => 3000,
                'paye_le' => '2026-11-06',
                'mode' => 'virement',
            ]);
            self::fail('Les paiements en attente doivent réserver le solde de la facture.');
        } catch (DomainException $exception) {
            $this->assertSame('Le paiement dépasse le solde restant de la facture.', $exception->getMessage());
        }

        $this->assertDatabaseCount('ci_paiements_fournisseurs', 1);
    }

    public function test_un_administrateur_ne_peut_pas_creer_un_fournisseur_dans_une_autre_compagnie(): void
    {
        $compagnieA = $this->creerCompagnie();
        $compagnieB = $this->creerCompagnie();
        $administrateur = $this->creerAdministrateur($compagnieA, '70000016');
        $workflow = app(CycleFournisseurs::class);

        try {
            $workflow->creerFournisseur($administrateur, $compagnieB->id, ['nom' => 'Hors périmètre']);
            self::fail('Un administrateur ne doit pas écrire dans une autre compagnie.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('ci_fournisseurs', 0);
        }
    }

    public function test_la_piece_de_facture_est_privee_et_limitee_a_sa_compagnie(): void
    {
        Storage::fake('local');
        $compagnieA = $this->creerCompagnie();
        $compagnieB = $this->creerCompagnie();
        $administrateurA = $this->creerAdministrateur($compagnieA, '70000017');
        $administrateurB = $this->creerAdministrateur($compagnieB, '70000018');
        $fournisseur = Fournisseur::create([
            'compagnie_id' => $compagnieA->id,
            'nom' => 'Fournisseur Pièce',
            'cree_par' => $administrateurA->id,
            'cree_par_nom' => $administrateurA->getFilamentName(),
        ]);
        $reception = ReceptionFournisseur::create([
            'compagnie_id' => $compagnieA->id,
            'fournisseur_id' => $fournisseur->id,
            'numero' => 'REC-2026-000001',
            'recu_le' => '2026-11-06',
            'statut' => 'validee',
            'cree_par' => $administrateurA->id,
            'cree_par_nom' => $administrateurA->getFilamentName(),
        ]);
        $chemin = 'audit/fournisseurs/facture-privee.pdf';
        Storage::disk('local')->put($chemin, 'contenu de la facture');
        $facture = FactureFournisseur::create([
            'compagnie_id' => $compagnieA->id,
            'fournisseur_id' => $fournisseur->id,
            'reception_id' => $reception->id,
            'numero' => 'FAC-2026-000001',
            'reference_fournisseur' => 'EXT-PRIVEE',
            'date_facture' => '2026-11-06',
            'montant' => 10000,
            'piece_path' => $chemin,
            'statut' => 'en_attente',
            'cree_par' => $administrateurA->id,
            'cree_par_nom' => $administrateurA->getFilamentName(),
        ]);

        $this->actingAs($administrateurA)
            ->get(route('cycle-fournisseurs.piece', ['facture' => $facture->id]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($administrateurB)
            ->get(route('cycle-fournisseurs.piece', ['facture' => $facture->id]))
            ->assertNotFound();
    }

    private function creerCompagnie(): Compagnie
    {
        $compagnie = Compagnie::factory()->create();
        Modules::definirCompagnie($compagnie->id, 'audit_interne', true);

        return $compagnie;
    }

    private function creerAdministrateur(Compagnie $compagnie, string $telephone): User
    {
        $administrateur = User::create([
            'prenom' => 'Admin',
            'nom' => 'Fournisseur',
            'telephone' => $telephone,
            'telephone_verifie' => true,
            'actif' => true,
            'password' => 'motdepasse123',
        ]);
        $administrateur->assignerRolePourCompagnie('admin_compagnie', $compagnie->id);

        return $administrateur;
    }
}
