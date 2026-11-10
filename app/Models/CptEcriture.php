<?php

namespace App\Models;

use App\Services\Compta;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class CptEcriture extends Model
{
    protected $table = 'cpt_ecritures';

    protected $guarded = [];

    protected $casts = [
        'date_ecriture' => 'date',
        'date_echeance' => 'date',
        'pieces' => 'array',
        'comptabilise_le' => 'datetime',
        'est_facture' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (CptEcriture $e) {
            if (blank($e->numero)) {
                $e->numero = Compta::prochainNumero((int) $e->journal_id, $e->date_ecriture);
            }
        });

        static::updating(function (CptEcriture $e) {
            if ($e->getOriginal('statut') === 'comptabilise'
                && $e->isDirty(['compagnie_id', 'exercice_id', 'journal_id', 'date_ecriture', 'libelle', 'reference', 'numero', 'numero_facture', 'code_kmaa'])) {
                throw new \DomainException('Une écriture comptabilisée ne peut plus être modifiée.');
            }
        });

        static::deleting(function (CptEcriture $e) {
            if ($e->statut === 'comptabilise') {
                throw new \DomainException('Une écriture comptabilisée ne se supprime pas : contre-passez-la.');
            }
        });
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(CptJournal::class, 'journal_id');
    }

    public function exercice(): BelongsTo
    {
        return $this->belongsTo(CptExercice::class, 'exercice_id');
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(CptLigne::class, 'ecriture_id')->orderBy('id');
    }

    /** Super admin : tout ; admin de compagnie (non limité à une ville) : sa compagnie. */
public function scopeVisiblePour(Builder $query, User $user): Builder
{
    if ($user->isSuperAdmin()) {
        return $query;
    }

    if ($user->isAdminCompagnie() && ! $user->villeId()) {
        return $query->where('compagnie_id', $user->compagnieId() ?? 0);
    }

    // Rôles métiers (comptable, auditeur) : leur compagnie, limitée à leurs gares.
    // Les écritures sans gare sont des écritures de compagnie : elles restent visibles.
    // Rôles métiers (comptable, auditeur) : toutes les écritures de leur compagnie.
    if ($user->roleDonneAccesAuModule('comptabilite') && $user->compagnieId()) {
        return $query->where('compagnie_id', $user->compagnieId());
    }

    return $query->whereRaw('1 = 0');
}

    /**
     * Valide UNE ligne. Les lignes se valident dans l'ordre (validation continue).
     * La dernière ligne validée comptabilise automatiquement l'écriture.
     * Saisie manuelle : le validateur doit être différent du créateur (RG-CPT-01).
     */
    public function validerLigne(User $acteur, ?int $ligneId = null): CptLigne
    {
        return DB::transaction(function () use ($acteur, $ligneId) {
            $e = static::query()->with('exercice')->whereKey($this->getKey())->lockForUpdate()->firstOrFail();

            if (! static::query()->visiblePour($acteur)->whereKey($e->id)->exists()) {
                throw new AuthorizationException('Vous n\'avez pas accès à cette écriture.');
            }

            if ($e->statut === 'comptabilise') {
                throw new \DomainException('Cette écriture est déjà comptabilisée.');
            }

            if ($e->exercice?->cloture) {
                throw new \DomainException('L\'exercice de cette écriture est clôturé.');
            }

            if ($e->origine === 'manuelle' && (int) $e->cree_par === (int) $acteur->id) {
                throw new \DomainException('Séparation des tâches : une saisie manuelle doit être validée par une autre personne que son auteur.');
            }

            $lignes = $e->lignes()->get();
            $debit = (int) $lignes->sum('debit');
            $credit = (int) $lignes->sum('credit');

            if ($lignes->count() < 2 || $debit === 0 || $debit !== $credit) {
                throw new \DomainException('L\'écriture doit avoir au moins deux lignes et un total débit égal au total crédit.');
            }

            $suivante = $lignes->first(fn (CptLigne $l) => ! $l->valide_le);

            if (! $suivante) {
                throw new \DomainException('Toutes les lignes sont déjà validées.');
            }

            if ($ligneId && (int) $suivante->id !== $ligneId) {
                $rang = $lignes->search(fn ($l) => (int) $l->id === (int) $suivante->id) + 1;
                throw new \DomainException('Les lignes se valident dans l\'ordre : validez d\'abord la ligne n° '.$rang.'.');
            }

            $suivante->forceFill(['valide_le' => now(), 'valide_par' => $acteur->id])->save();

            if ($lignes->every(fn (CptLigne $l) => (bool) $l->valide_le)) {
                $e->forceFill(['statut' => 'comptabilise', 'comptabilise_le' => now()])->save();
            }

            return $suivante;
        });
    }

    public function validerProchaineLigne(User $acteur): CptLigne
    {
        return $this->validerLigne($acteur, null);
    }

    /** Valide d'un coup toutes les lignes restantes (une par une, dans l'ordre). */
        /** Comptabilise l'écriture d'un coup, sans circuit de validation. */
    public function comptabiliser(?User $acteur = null): self
    {
        return DB::transaction(function () use ($acteur) {
            $e = static::query()->with('exercice')->whereKey($this->getKey())->lockForUpdate()->firstOrFail();

            if ($acteur && ! static::query()->visiblePour($acteur)->whereKey($e->id)->exists()) {
                throw new AuthorizationException('Vous n\'avez pas accès à cette écriture.');
            }

            if ($e->statut === 'comptabilise') {
                throw new \DomainException('Cette écriture est déjà comptabilisée.');
            }

            if ($e->exercice?->cloture) {
                throw new \DomainException('L\'exercice de cette écriture est clôturé.');
            }

            $lignes = $e->lignes()->get();
            $debit = (int) $lignes->sum('debit');
            $credit = (int) $lignes->sum('credit');

            if ($lignes->count() < 2 || $debit === 0 || $debit !== $credit) {
                throw new \DomainException('L\'écriture doit avoir au moins deux lignes et un total débit égal au total crédit.');
            }

            CptLigne::query()->where('ecriture_id', $e->id)
                ->update(['valide_le' => now(), 'valide_par' => $acteur?->id]);

            $e->forceFill(['statut' => 'comptabilise', 'comptabilise_le' => now()])->save();

            return $e;
        });
    }

    /** Corrige une écriture comptabilisée en passant l'écriture inverse, à la date du jour. */
    public function contrepasser(User $acteur): self
    {
        return DB::transaction(function () use ($acteur) {
            $o = static::query()->with('lignes.imputations')->whereKey($this->getKey())->lockForUpdate()->firstOrFail();

            if (! static::query()->visiblePour($acteur)->whereKey($o->id)->exists()) {
                throw new AuthorizationException('Vous n\'avez pas accès à cette écriture.');
            }

            if ($o->statut !== 'comptabilise') {
                throw new \DomainException('Seule une écriture comptabilisée peut être contre-passée.');
            }

            if (static::where('contrepasse_de', $o->id)->exists()) {
                throw new \DomainException('Cette écriture a déjà été contre-passée.');
            }

            $exercice = Compta::exerciceOuvert((int) $o->compagnie_id, today());

            if (! $exercice) {
                throw new \DomainException('Aucun exercice ouvert à la date du jour.');
            }

            $c = static::create([
                'compagnie_id' => $o->compagnie_id,
                'exercice_id' => $exercice->id,
                'journal_id' => $o->journal_id,
                'date_ecriture' => today(),
                'libelle' => 'Contre-passation de '.$o->numero.' · '.$o->libelle,
                'reference' => $o->reference,
                'gare_id' => $o->gare_id,
                'tiers' => $o->tiers,
                'origine' => 'contrepassation',
                'source_type' => static::class,
                'source_id' => $o->id,
                'contrepasse_de' => $o->id,
                'cree_par' => $acteur->id,
            ]);

            foreach ($o->lignes as $l) {
                $nl = CptLigne::create([
                    'ecriture_id' => $c->id,
                    'compte_id' => $l->compte_id,
                    'libelle' => $l->libelle,
                    'debit' => $l->credit,
                    'credit' => $l->debit,
                    'section_id' => $l->section_id,
                    'valide_le' => now(),
                    'valide_par' => $acteur->id,
                ]);

                foreach ($l->imputations as $im) {
                    CptImputation::create([
                        'compagnie_id' => $o->compagnie_id,
                        'ligne_id' => $nl->id,
                        'section_id' => $im->section_id,
                        'montant' => $im->montant,
                        'pourcentage' => $im->pourcentage,
                        'note' => $im->note,
                    ]);
                }
            }

            $c->forceFill(['statut' => 'comptabilise', 'comptabilise_le' => now()])->save();

            return $c;
        });
    }
}
