<?php

namespace App\Services\AuditInterne;

use App\Models\ArticleStock;
use App\Models\Compagnie;
use App\Models\FactureFournisseur;
use App\Models\Fournisseur;
use App\Models\MouvementStockFournisseur;
use App\Models\PaiementFournisseur;
use App\Models\ReceptionFournisseur;
use App\Models\SequenceDocument;
use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CycleFournisseurs
{
    private const MODES_PAIEMENT = ['especes', 'cheque', 'virement', 'mobile_money', 'autre'];

    /**
     * @param  array{nom: string, identifiant_fiscal?: ?string, telephone?: ?string, email?: ?string, adresse?: ?string}  $donnees
     */
    public function creerFournisseur(User $acteur, int $compagnieId, array $donnees): Fournisseur
    {
        $this->autoriser($acteur, $compagnieId);

        return Fournisseur::create([
            'compagnie_id' => $compagnieId,
            'nom' => trim($donnees['nom']),
            'identifiant_fiscal' => $donnees['identifiant_fiscal'] ?? null,
            'telephone' => $donnees['telephone'] ?? null,
            'email' => $donnees['email'] ?? null,
            'adresse' => $donnees['adresse'] ?? null,
            'cree_par' => $acteur->id,
            'cree_par_nom' => $acteur->getFilamentName(),
        ]);
    }

    /**
     * @param  array{reference?: ?string, nom: string, unite: string}  $donnees
     */
    public function creerArticleStock(User $acteur, int $compagnieId, array $donnees): ArticleStock
    {
        $this->autoriser($acteur, $compagnieId);

        return ArticleStock::create([
            'compagnie_id' => $compagnieId,
            'reference' => filled($donnees['reference'] ?? null) ? trim($donnees['reference']) : null,
            'nom' => trim($donnees['nom']),
            'unite' => trim($donnees['unite']),
            'cree_par' => $acteur->id,
        ]);
    }

    /**
     * @param  array{fournisseur_id: int, recu_le: string, description?: ?string, lignes: array<int, array{article_id: int, quantite: int|float|string}>}  $donnees
     */
    public function creerReception(User $acteur, int $compagnieId, array $donnees): ReceptionFournisseur
    {
        $this->autoriser($acteur, $compagnieId);

        return DB::transaction(function () use ($acteur, $compagnieId, $donnees): ReceptionFournisseur {
            $fournisseur = Fournisseur::query()
                ->where('compagnie_id', $compagnieId)
                ->where('actif', true)
                ->findOrFail((int) $donnees['fournisseur_id']);

            if ($donnees['lignes'] === []) {
                throw new DomainException('Une réception doit contenir au moins un article.');
            }

            $articleIds = collect($donnees['lignes'])->pluck('article_id')->map(fn ($id): int => (int) $id);

            if ($articleIds->unique()->count() !== $articleIds->count()) {
                throw new DomainException('Un article ne peut apparaître qu’une seule fois dans une réception.');
            }

            $articles = ArticleStock::query()
                ->where('compagnie_id', $compagnieId)
                ->whereIn('id', $articleIds)
                ->get()
                ->keyBy('id');

            if ($articles->count() !== $articleIds->count()) {
                throw new AuthorizationException('Un article sélectionné ne relève pas de cette compagnie.');
            }

            $reception = ReceptionFournisseur::create([
                'compagnie_id' => $compagnieId,
                'fournisseur_id' => $fournisseur->id,
                'numero' => $this->prochainNumero($compagnieId, 'REC', $donnees['recu_le']),
                'recu_le' => $donnees['recu_le'],
                'description' => filled($donnees['description'] ?? null) ? trim($donnees['description']) : null,
                'statut' => 'en_attente',
                'cree_par' => $acteur->id,
                'cree_par_nom' => $acteur->getFilamentName(),
            ]);

            foreach ($donnees['lignes'] as $ligne) {
                $quantite = round((float) $ligne['quantite'], 3);

                if ($quantite <= 0) {
                    throw new DomainException('La quantité reçue doit être supérieure à zéro.');
                }

                $reception->lignes()->create([
                    'article_id' => (int) $ligne['article_id'],
                    'quantite' => number_format($quantite, 3, '.', ''),
                ]);
            }

            return $reception;
        });
    }

    public function validerReception(User $acteur, int $compagnieId, int $receptionId): ReceptionFournisseur
    {
        $this->autoriser($acteur, $compagnieId);

        return DB::transaction(function () use ($acteur, $compagnieId, $receptionId): ReceptionFournisseur {
            $reception = ReceptionFournisseur::query()
                ->where('compagnie_id', $compagnieId)
                ->with(['fournisseur', 'lignes'])
                ->lockForUpdate()
                ->findOrFail($receptionId);

            if ($reception->statut !== 'en_attente') {
                throw new DomainException('Cette réception a déjà été traitée.');
            }

            $this->verifierActeursDistincts($acteur, [
                $reception->fournisseur->cree_par,
                $reception->cree_par,
            ], 'La réception doit être validée par une personne différente de son créateur.');

            foreach ($reception->lignes->sortBy('article_id') as $ligne) {
                $article = ArticleStock::query()
                    ->where('compagnie_id', $compagnieId)
                    ->lockForUpdate()
                    ->findOrFail($ligne->article_id);
                $quantiteMilli = (int) round(((float) $ligne->quantite) * 1000);
                $stockMilli = (int) round(((float) $article->quantite_stock) * 1000);

                $article->update([
                    'quantite_stock' => number_format(($stockMilli + $quantiteMilli) / 1000, 3, '.', ''),
                ]);

                MouvementStockFournisseur::create([
                    'compagnie_id' => $compagnieId,
                    'article_id' => $article->id,
                    'reception_id' => $reception->id,
                    'ligne_reception_id' => $ligne->id,
                    'type' => 'entree',
                    'quantite' => $ligne->quantite,
                    'cree_par' => $acteur->id,
                    'cree_par_nom' => $acteur->getFilamentName(),
                    'cree_le' => now(),
                ]);
            }

            $reception->update([
                'statut' => 'validee',
                'valide_par' => $acteur->id,
                'valide_par_nom' => $acteur->getFilamentName(),
                'valide_le' => now(),
            ]);

            return $reception->refresh();
        });
    }

    /**
     * @param  array{reception_id: int, reference_fournisseur: string, date_facture: string, echeance?: ?string, montant: int, piece_path?: ?string}  $donnees
     */
    public function creerFacture(User $acteur, int $compagnieId, array $donnees): FactureFournisseur
    {
        $this->autoriser($acteur, $compagnieId);

        return DB::transaction(function () use ($acteur, $compagnieId, $donnees): FactureFournisseur {
            $reception = ReceptionFournisseur::query()
                ->where('compagnie_id', $compagnieId)
                ->with('fournisseur')
                ->lockForUpdate()
                ->findOrFail((int) $donnees['reception_id']);

            if ($reception->statut !== 'validee') {
                throw new DomainException('Une facture ne peut être enregistrée qu’après validation de la réception.');
            }

            $this->verifierActeursDistincts($acteur, [
                $reception->fournisseur->cree_par,
                $reception->cree_par,
                $reception->valide_par,
            ], 'La réception et la facture doivent être traitées par des personnes différentes.');

            $montant = (int) $donnees['montant'];

            if ($montant < 1) {
                throw new DomainException('Le montant de la facture doit être supérieur à zéro.');
            }

            return FactureFournisseur::create([
                'compagnie_id' => $compagnieId,
                'fournisseur_id' => $reception->fournisseur_id,
                'reception_id' => $reception->id,
                'numero' => $this->prochainNumero($compagnieId, 'FAC', $donnees['date_facture']),
                'reference_fournisseur' => trim($donnees['reference_fournisseur']),
                'date_facture' => $donnees['date_facture'],
                'echeance' => $donnees['echeance'] ?? null,
                'montant' => $montant,
                'piece_path' => $donnees['piece_path'] ?? null,
                'statut' => 'en_attente',
                'cree_par' => $acteur->id,
                'cree_par_nom' => $acteur->getFilamentName(),
            ]);
        });
    }

    public function validerFacture(User $acteur, int $compagnieId, int $factureId): FactureFournisseur
    {
        $this->autoriser($acteur, $compagnieId);

        return DB::transaction(function () use ($acteur, $compagnieId, $factureId): FactureFournisseur {
            $facture = FactureFournisseur::query()
                ->where('compagnie_id', $compagnieId)
                ->with(['fournisseur', 'reception'])
                ->lockForUpdate()
                ->findOrFail($factureId);

            if ($facture->statut !== 'en_attente') {
                throw new DomainException('Cette facture a déjà été traitée.');
            }

            $this->verifierActeursDistincts($acteur, [
                $facture->fournisseur->cree_par,
                $facture->reception->cree_par,
                $facture->reception->valide_par,
                $facture->cree_par,
            ], 'La facture doit être validée par une personne distincte des intervenants précédents.');

            $facture->update([
                'statut' => 'validee',
                'valide_par' => $acteur->id,
                'valide_par_nom' => $acteur->getFilamentName(),
                'valide_le' => now(),
            ]);

            return $facture->refresh();
        });
    }

    /**
     * @param  array{facture_id: int, montant: int, paye_le: string, mode: string, reference?: ?string}  $donnees
     */
    public function creerPaiement(User $acteur, int $compagnieId, array $donnees): PaiementFournisseur
    {
        $this->autoriser($acteur, $compagnieId);

        return DB::transaction(function () use ($acteur, $compagnieId, $donnees): PaiementFournisseur {
            $facture = FactureFournisseur::query()
                ->where('compagnie_id', $compagnieId)
                ->with(['fournisseur', 'reception'])
                ->lockForUpdate()
                ->findOrFail((int) $donnees['facture_id']);

            if (! in_array($facture->statut, ['validee', 'partiellement_reglee'], true)) {
                throw new DomainException('Seule une facture validée et non soldée peut être payée.');
            }

            $this->verifierActeursDistincts($acteur, [
                $facture->fournisseur->cree_par,
                $facture->reception->cree_par,
                $facture->reception->valide_par,
                $facture->cree_par,
                $facture->valide_par,
            ], 'La création du fournisseur et le paiement doivent être effectués par des personnes différentes.');

            $montant = (int) $donnees['montant'];

            if ($montant < 1 || ! in_array($donnees['mode'], self::MODES_PAIEMENT, true)) {
                throw new DomainException('Le montant ou le mode de paiement est invalide.');
            }

            if ($this->montantReserve($facture) + $montant > $facture->montant) {
                throw new DomainException('Le paiement dépasse le solde restant de la facture.');
            }

            return PaiementFournisseur::create([
                'compagnie_id' => $compagnieId,
                'facture_id' => $facture->id,
                'numero' => $this->prochainNumero($compagnieId, 'PAY', $donnees['paye_le']),
                'montant' => $montant,
                'paye_le' => $donnees['paye_le'],
                'mode' => $donnees['mode'],
                'reference' => $donnees['reference'] ?? null,
                'statut' => 'en_attente',
                'cree_par' => $acteur->id,
                'cree_par_nom' => $acteur->getFilamentName(),
            ]);
        });
    }

    public function validerPaiement(User $acteur, int $compagnieId, int $paiementId): PaiementFournisseur
    {
        $this->autoriser($acteur, $compagnieId);

        return DB::transaction(function () use ($acteur, $compagnieId, $paiementId): PaiementFournisseur {
            $paiement = PaiementFournisseur::query()
                ->where('compagnie_id', $compagnieId)
                ->lockForUpdate()
                ->findOrFail($paiementId);
            $facture = FactureFournisseur::query()
                ->where('compagnie_id', $compagnieId)
                ->with(['fournisseur', 'reception'])
                ->lockForUpdate()
                ->findOrFail($paiement->facture_id);

            if ($paiement->statut !== 'en_attente') {
                throw new DomainException('Ce paiement a déjà été traité.');
            }

            $this->verifierActeursDistincts($acteur, [
                $paiement->cree_par,
                $facture->fournisseur->cree_par,
                $facture->reception->cree_par,
                $facture->reception->valide_par,
                $facture->cree_par,
                $facture->valide_par,
            ], 'Le paiement doit être validé par une personne distincte des intervenants précédents.');

            if ($this->montantReserve($facture) > $facture->montant) {
                throw new DomainException('Le total des paiements réservés dépasse le montant de la facture.');
            }

            $paiement->update([
                'statut' => 'valide',
                'valide_par' => $acteur->id,
                'valide_par_nom' => $acteur->getFilamentName(),
                'valide_le' => now(),
            ]);

            $montantRegle = (int) $facture->paiements()->where('statut', 'valide')->sum('montant');
            $facture->update([
                'statut' => $montantRegle >= $facture->montant
                    ? 'reglee'
                    : 'partiellement_reglee',
            ]);

            return $paiement->refresh();
        });
    }

    private function autoriser(User $acteur, int $compagnieId): void
    {
        if (! Acces::visible($acteur)
            || ! Compagnie::query()->whereKey($compagnieId)->exists()
            || (! $acteur->isSuperAdmin() && (int) $acteur->compagnieId() !== $compagnieId)) {
            throw new AuthorizationException('Vous n’avez pas accès aux données de cette compagnie.');
        }
    }

    /**
     * @param  array<int, int|null>  $identifiants
     */
    private function verifierActeursDistincts(User $acteur, array $identifiants, string $message): void
    {
        if (in_array((int) $acteur->id, array_map('intval', array_filter($identifiants)), true)) {
            throw new DomainException($message);
        }
    }

    private function prochainNumero(int $compagnieId, string $serie, string $date): string
    {
        Compagnie::query()->whereKey($compagnieId)->lockForUpdate()->firstOrFail();

        $annee = (int) Carbon::parse($date)->format('Y');
        $sequence = SequenceDocument::query()
            ->where('compagnie_id', $compagnieId)
            ->where('serie', $serie)
            ->where('annee', $annee)
            ->lockForUpdate()
            ->first();

        if ($sequence === null) {
            $sequence = SequenceDocument::create([
                'compagnie_id' => $compagnieId,
                'serie' => $serie,
                'annee' => $annee,
                'dernier_numero' => 1,
            ]);
        } else {
            $sequence->increment('dernier_numero');
            $sequence->refresh();
        }

        return sprintf('%s-%d-%06d', $serie, $annee, $sequence->dernier_numero);
    }

    private function montantReserve(FactureFournisseur $facture): int
    {
        return (int) $facture->paiements()
            ->whereIn('statut', ['en_attente', 'valide'])
            ->sum('montant');
    }
}
