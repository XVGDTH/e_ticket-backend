<?php

namespace App\Services;

use App\Models\Compagnie;
use App\Models\CptAmortissement;
use App\Models\CptCategorieImmobilisation;
use App\Models\CptCompte;
use App\Models\CptEcriture;
use App\Models\CptExercice;
use App\Models\CptImmobilisation;
use App\Models\CptJournal;
use App\Models\CptLigne;
use App\Models\User;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator as ValidationValidator;

class ImmobilisationsComptables
{
    private const MAX_AMOUNT = 1000000000000;

    private const FIRST_PERIOD_RULES = [
        'prorata_temporis',
        'mois_complet',
        'mois_suivant',
    ];

    public function optionsComptes(int $compagnieId, string $type): array
    {
        $prefix = match ($type) {
            'actif' => '2',
            'amortissement' => '28',
            'charge' => '681',
            default => null,
        };

        if (! $compagnieId || ! $prefix) {
            return [];
        }

        return CptCompte::query()
            ->where('actif', true)
            ->where('numero', 'like', $prefix.'%')
            ->where(fn ($query) => $query->whereNull('compagnie_id')->orWhere('compagnie_id', $compagnieId))
            ->orderBy('numero')
            ->get()
            ->mapWithKeys(fn (CptCompte $compte) => [
                $compte->id => $compte->numero.' · '.$compte->intitule,
            ])
            ->all();
    }

    public function creerCategorie(User $acteur, int $compagnieId, array $donnees): CptCategorieImmobilisation
    {
        $this->autoriser($acteur, $compagnieId);

        $validees = Validator::make($donnees, [
            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('cpt_categories_immobilisations', 'code')->where('compagnie_id', $compagnieId),
            ],
            'libelle' => ['required', 'string', 'max:120'],
            'duree_utilite_mois' => ['required', 'integer', 'between:1,600'],
            'mode_amortissement' => ['required', Rule::in(['lineaire'])],
            'premiere_periode' => ['required', Rule::in(self::FIRST_PERIOD_RULES)],
            'compte_actif_id' => ['required', 'integer', 'exists:cpt_comptes,id'],
            'compte_amortissement_id' => ['required', 'integer', 'exists:cpt_comptes,id'],
            'compte_charge_id' => ['required', 'integer', 'exists:cpt_comptes,id'],
        ])->validate();

        $this->verifierComptes($compagnieId, $validees);

        return CptCategorieImmobilisation::query()->create([
            ...$validees,
            'compagnie_id' => $compagnieId,
            'actif' => true,
            'cree_par' => $acteur->id,
        ]);
    }

    public function modifierCategorie(
        User $acteur,
        int $compagnieId,
        int $categorieId,
        array $donnees
    ): CptCategorieImmobilisation {
        $this->autoriser($acteur, $compagnieId);

        $categorie = CptCategorieImmobilisation::query()
            ->where('compagnie_id', $compagnieId)
            ->lockForUpdate()
            ->findOrFail($categorieId);

        $validees = Validator::make($donnees, [
            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('cpt_categories_immobilisations', 'code')
                    ->where('compagnie_id', $compagnieId)
                    ->ignore($categorie->id),
            ],
            'libelle' => ['required', 'string', 'max:120'],
            'duree_utilite_mois' => ['required', 'integer', 'between:1,600'],
            'mode_amortissement' => ['required', Rule::in(['lineaire'])],
            'premiere_periode' => ['required', Rule::in(self::FIRST_PERIOD_RULES)],
            'compte_actif_id' => ['required', 'integer', 'exists:cpt_comptes,id'],
            'compte_amortissement_id' => ['required', 'integer', 'exists:cpt_comptes,id'],
            'compte_charge_id' => ['required', 'integer', 'exists:cpt_comptes,id'],
            'actif' => ['required', 'boolean'],
        ])->validate();

        $this->verifierComptes($compagnieId, $validees);
        $categorie->update($validees);

        return $categorie->refresh();
    }

    public function creerImmobilisation(User $acteur, int $compagnieId, array $donnees): CptImmobilisation
    {
        $this->autoriser($acteur, $compagnieId);

        $validees = Validator::make($donnees, [
            'categorie_id' => ['required', 'integer', 'exists:cpt_categories_immobilisations,id'],
            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('cpt_immobilisations', 'code')->where('compagnie_id', $compagnieId),
            ],
            'designation' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:5000'],
            'date_acquisition' => ['required', 'date'],
            'date_mise_en_service' => ['required', 'date'],
            'cout_acquisition' => ['required', 'integer', 'between:1,'.self::MAX_AMOUNT],
            'valeur_residuelle' => ['required', 'integer', 'between:0,'.self::MAX_AMOUNT],
        ])->after(function (ValidationValidator $validator) use ($donnees): void {
            if ($validator->errors()->hasAny(['date_acquisition', 'date_mise_en_service', 'cout_acquisition', 'valeur_residuelle'])) {
                return;
            }

            if (
                filled($donnees['date_acquisition'] ?? null)
                && filled($donnees['date_mise_en_service'] ?? null)
                && Carbon::parse($donnees['date_mise_en_service'])->lt(Carbon::parse($donnees['date_acquisition']))
            ) {
                $validator->errors()->add('date_mise_en_service', 'La mise en service ne peut pas précéder l’acquisition.');
            }

            if (
                isset($donnees['cout_acquisition'], $donnees['valeur_residuelle'])
                && (int) $donnees['valeur_residuelle'] > (int) $donnees['cout_acquisition']
            ) {
                $validator->errors()->add('valeur_residuelle', 'La valeur résiduelle ne peut pas dépasser le coût d’acquisition.');
            }
        })->validate();

        $categorie = CptCategorieImmobilisation::query()
            ->where('compagnie_id', $compagnieId)
            ->where('actif', true)
            ->find($validees['categorie_id']);

        if (! $categorie || ! $categorie->duree_utilite_mois) {
            throw ValidationException::withMessages([
                'categorie_id' => 'Choisissez une catégorie active et entièrement paramétrée pour la compagnie.',
            ]);
        }

        $this->verifierComptes($compagnieId, $categorie->only([
            'compte_actif_id',
            'compte_amortissement_id',
            'compte_charge_id',
        ]));

        return CptImmobilisation::query()->create([
            'compagnie_id' => $compagnieId,
            'categorie_id' => $categorie->id,
            'code' => $validees['code'],
            'designation' => $validees['designation'],
            'description' => $validees['description'] ?? null,
            'date_acquisition' => $validees['date_acquisition'],
            'date_mise_en_service' => $validees['date_mise_en_service'],
            'cout_acquisition' => (int) $validees['cout_acquisition'],
            'valeur_residuelle' => (int) $validees['valeur_residuelle'],
            'duree_utilite_mois' => $categorie->duree_utilite_mois,
            'mode_amortissement' => $categorie->mode_amortissement,
            'premiere_periode' => $categorie->premiere_periode,
            'compte_actif_id' => $categorie->compte_actif_id,
            'compte_amortissement_id' => $categorie->compte_amortissement_id,
            'compte_charge_id' => $categorie->compte_charge_id,
            'statut' => 'brouillon',
            'cree_par' => $acteur->id,
        ]);
    }

    public function validerImmobilisation(User $acteur, int $compagnieId, int $immobilisationId): CptImmobilisation
    {
        $this->autoriser($acteur, $compagnieId);

        return DB::transaction(function () use ($acteur, $compagnieId, $immobilisationId): CptImmobilisation {
            $immobilisation = CptImmobilisation::query()
                ->where('compagnie_id', $compagnieId)
                ->lockForUpdate()
                ->findOrFail($immobilisationId);

            if ($immobilisation->statut !== 'brouillon') {
                throw new DomainException('Seule une immobilisation en brouillon peut être validée.');
            }

            if ((int) $immobilisation->cree_par === (int) $acteur->id) {
                throw new DomainException('L’immobilisation doit être validée par une personne différente de son créateur.');
            }

            $this->creerEcheancier($immobilisation);
            $immobilisation->update([
                'statut' => 'active',
                'valide_par' => $acteur->id,
                'validee_le' => now(),
            ]);

            return $immobilisation->refresh();
        });
    }

    public function proposerEcriture(User $acteur, int $compagnieId, int $amortissementId): CptAmortissement
    {
        $this->autoriser($acteur, $compagnieId);

        return DB::transaction(function () use ($acteur, $compagnieId, $amortissementId): CptAmortissement {
            $amortissement = CptAmortissement::query()
                ->where('compagnie_id', $compagnieId)
                ->with('immobilisation')
                ->lockForUpdate()
                ->findOrFail($amortissementId);

            if ($amortissement->statut !== 'planifie') {
                throw new DomainException('Seule une période planifiée peut produire une écriture.');
            }

            if ($amortissement->montant < 1) {
                throw new DomainException('Le montant amortissable de cette période est nul.');
            }

            if ($amortissement->periode_fin->isFuture()) {
                throw new DomainException('Une écriture d’amortissement ne peut pas être proposée avant la fin de sa période.');
            }

            $exercice = $this->exerciceOuvert($compagnieId, $amortissement->periode_fin);
            $this->verifierComptes($compagnieId, $amortissement->immobilisation->only([
                'compte_actif_id',
                'compte_amortissement_id',
                'compte_charge_id',
            ]));

            $journal = CptJournal::query()
                ->where('compagnie_id', $compagnieId)
                ->where('code', 'OD')
                ->firstOrFail();
            $reference = $this->referenceAmortissement($amortissement);
            $ecriture = CptEcriture::query()->create([
                'compagnie_id' => $compagnieId,
                'exercice_id' => $exercice->id,
                'journal_id' => $journal->id,
                'date_ecriture' => $amortissement->periode_fin->toDateString(),
                'libelle' => Str::limit(
                    'Dotation amortissement '.$amortissement->immobilisation->code.' · '.$amortissement->immobilisation->designation,
                    160,
                    ''
                ),
                'reference' => $reference,
                'cree_par' => $acteur->id,
            ]);

            CptLigne::query()->create([
                'ecriture_id' => $ecriture->id,
                'compte_id' => $amortissement->immobilisation->compte_charge_id,
                'libelle' => 'Dotation amortissement '.$amortissement->periode_fin->format('m/Y'),
                'debit' => $amortissement->montant,
                'credit' => 0,
            ]);
            CptLigne::query()->create([
                'ecriture_id' => $ecriture->id,
                'compte_id' => $amortissement->immobilisation->compte_amortissement_id,
                'libelle' => 'Amortissement cumulé '.$amortissement->immobilisation->code,
                'debit' => 0,
                'credit' => $amortissement->montant,
            ]);

            $amortissement->update([
                'statut' => 'brouillon',
                'ecriture_id' => $ecriture->id,
                'cree_par' => $acteur->id,
            ]);

            return $amortissement->refresh();
        });
    }

    public function comptabiliserAmortissement(User $acteur, int $compagnieId, int $amortissementId): CptAmortissement
    {
        $this->autoriser($acteur, $compagnieId);

        return DB::transaction(function () use ($acteur, $compagnieId, $amortissementId): CptAmortissement {
            $amortissement = CptAmortissement::query()
                ->where('compagnie_id', $compagnieId)
                ->with(['immobilisation', 'ecriture'])
                ->lockForUpdate()
                ->findOrFail($amortissementId);

            if ($amortissement->statut !== 'brouillon' || ! $amortissement->ecriture) {
                throw new DomainException('Seule une proposition d’amortissement en brouillon peut être comptabilisée.');
            }

            if ((int) $amortissement->cree_par === (int) $acteur->id) {
                throw new DomainException('La proposition d’amortissement doit être comptabilisée par une personne différente de son préparateur.');
            }

            $exercice = $this->exerciceOuvert($compagnieId, $amortissement->periode_fin);
            $this->verifierBrouillonIntact($amortissement);

            if ((int) $amortissement->ecriture->exercice_id !== (int) $exercice->id) {
                throw new DomainException('L’exercice de l’écriture ne correspond plus à la période d’amortissement.');
            }

            $amortissement->ecriture->comptabiliser($acteur);
            $amortissement->update([
                'statut' => 'comptabilise',
                'valide_par' => $acteur->id,
                'validee_le' => now(),
            ]);

            return $amortissement->refresh();
        });
    }

    private function autoriser(User $acteur, int $compagnieId): void
    {
        abort_unless(
            Modules::visiblePour($acteur, 'comptabilite')
                && ($acteur->isSuperAdmin()
                    || ($acteur->isAdminCompagnie()
                        && ! $acteur->villeId()
                        && (int) $acteur->compagnieId() === $compagnieId)),
            403
        );

        Compagnie::query()->findOrFail($compagnieId);
    }

    private function verifierComptes(int $compagnieId, array $donnees): void
    {
        foreach ([
            'compte_actif_id' => '2',
            'compte_amortissement_id' => '28',
            'compte_charge_id' => '681',
        ] as $champ => $prefixe) {
            $compte = CptCompte::query()
                ->whereKey($donnees[$champ] ?? 0)
                ->where('actif', true)
                ->where('numero', 'like', $prefixe.'%')
                ->where(fn ($query) => $query->whereNull('compagnie_id')->orWhere('compagnie_id', $compagnieId))
                ->first();

            if (! $compte) {
                throw ValidationException::withMessages([
                    $champ => 'Le compte choisi est inactif, hors compagnie ou incompatible avec ce type d’immobilisation.',
                ]);
            }
        }
    }

    private function exerciceOuvert(int $compagnieId, Carbon $date): CptExercice
    {
        Compta::initialiser($compagnieId);

        return Compta::exerciceOuvert($compagnieId, $date)
            ?? throw new DomainException('Aucun exercice ouvert ne couvre la période de cet amortissement.');
    }

    private function creerEcheancier(CptImmobilisation $immobilisation): void
    {
        $baseAmortissable = $immobilisation->cout_acquisition - $immobilisation->valeur_residuelle;

        if ($baseAmortissable <= 0) {
            return;
        }

        $miseEnService = $immobilisation->date_mise_en_service->copy()->startOfDay();
        $debut = match ($immobilisation->premiere_periode) {
            'mois_complet' => $miseEnService->copy()->startOfMonth(),
            'mois_suivant' => $miseEnService->copy()->startOfMonth()->addMonthNoOverflow(),
            default => $miseEnService,
        };
        $finExclusive = $debut->copy()->addMonthsNoOverflow($immobilisation->duree_utilite_mois);
        $joursTotaux = (int) $debut->diffInDays($finExclusive);

        if ($joursTotaux < 1) {
            throw new DomainException('La durée d’amortissement configurée ne produit aucune période valide.');
        }

        $joursEcoules = 0;
        $montantReparti = 0;
        $curseur = $debut->copy();

        while ($curseur->lt($finExclusive)) {
            $finPeriode = $curseur->copy()->endOfMonth()->startOfDay();
            $dernierJour = $finExclusive->copy()->subDay()->startOfDay();

            if ($finPeriode->gt($dernierJour)) {
                $finPeriode = $dernierJour;
            }

            $joursPeriode = (int) $curseur->diffInDays($finPeriode->copy()->addDay()->startOfDay());
            $joursEcoules += $joursPeriode;
            $cibleCumulee = $joursEcoules >= $joursTotaux
                ? $baseAmortissable
                : intdiv($baseAmortissable * $joursEcoules, $joursTotaux);
            $montantPeriode = $cibleCumulee - $montantReparti;

            if ($montantPeriode > 0) {
                CptAmortissement::query()->create([
                    'compagnie_id' => $immobilisation->compagnie_id,
                    'immobilisation_id' => $immobilisation->id,
                    'periode_debut' => $curseur->toDateString(),
                    'periode_fin' => $finPeriode->toDateString(),
                    'montant' => $montantPeriode,
                    'statut' => 'planifie',
                ]);
            }

            $montantReparti = $cibleCumulee;
            $curseur = $finPeriode->copy()->addDay()->startOfDay();
        }

        if ($montantReparti !== $baseAmortissable) {
            throw new DomainException('Le plan d’amortissement ne répartit pas exactement la base amortissable.');
        }
    }

    private function verifierBrouillonIntact(CptAmortissement $amortissement): void
    {
        $ecriture = $amortissement->ecriture;
        $immobilisation = $amortissement->immobilisation;
        $exercice = $this->exerciceOuvert($amortissement->compagnie_id, $amortissement->periode_fin);
        $journalValide = CptJournal::query()
            ->whereKey($ecriture->journal_id)
            ->where('compagnie_id', $amortissement->compagnie_id)
            ->where('code', 'OD')
            ->exists();
        $lignes = $ecriture->lignes()->get();
        $attendues = collect([
            [
                'compte_id' => (int) $immobilisation->compte_charge_id,
                'debit' => (int) $amortissement->montant,
                'credit' => 0,
            ],
            [
                'compte_id' => (int) $immobilisation->compte_amortissement_id,
                'debit' => 0,
                'credit' => (int) $amortissement->montant,
            ],
        ])->sortBy('compte_id')->values();
        $actuelles = $lignes->map(fn (CptLigne $ligne) => [
            'compte_id' => (int) $ligne->compte_id,
            'debit' => (int) $ligne->debit,
            'credit' => (int) $ligne->credit,
        ])->sortBy('compte_id')->values();
        $reference = $this->referenceAmortissement($amortissement);

        if ($ecriture->statut !== 'brouillon') {
            throw new DomainException('L’écriture liée n’est plus un brouillon.');
        }

        if (
            (int) $ecriture->compagnie_id !== (int) $amortissement->compagnie_id
            || $ecriture->date_ecriture->toDateString() !== $amortissement->periode_fin->toDateString()
            || $ecriture->reference !== $reference
            || ! $journalValide
            || (int) $ecriture->exercice_id !== (int) $exercice->id
        ) {
            throw new DomainException('L’écriture liée a été modifiée. Vérifiez ou recréez la proposition avant comptabilisation.');
        }

        if ($actuelles->all() !== $attendues->all()) {
            throw new DomainException('Les lignes de l’écriture liée ont été modifiées. Recréez la proposition avant comptabilisation.');
        }
    }

    private function referenceAmortissement(CptAmortissement $amortissement): string
    {
        return 'AMO-'.$amortissement->immobilisation->code.'-'.$amortissement->periode_fin->format('Ym');
    }
}
