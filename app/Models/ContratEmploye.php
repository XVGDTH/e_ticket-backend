<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContratEmploye extends Model
{
    protected $table = 'contrats_employes';

    protected $guarded = [];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date',
        'fin_essai' => 'date',
        'elements_fixes' => 'array',
        'actif' => 'boolean',
        'cadre' => 'boolean',
        'salaire_base' => 'integer',
        'heures_hebdo' => 'integer',
    ];

    public const TYPES = [
        'cdi' => 'CDI',
        'cdd' => 'CDD',
        'journalier' => 'Journalier / temporaire',
        'stage' => 'Stage',
        'apprentissage' => 'Apprentissage',
        'prestation' => 'Prestation (consultant)',
    ];

    public const MODES = ['mensuel' => 'Salaire mensuel', 'journalier' => 'Taux par jour travaillé'];

    protected static function booted(): void
    {
        static::creating(fn (ContratEmploye $c) => $c->cree_par ??= auth()->id());

        // Un seul contrat actif par employé.
        static::saving(function (ContratEmploye $c) {
            if ($c->actif) {
                static::query()->where('employe_id', $c->employe_id)
                    ->when($c->exists, fn ($q) => $q->whereKeyNot($c->id))
                    ->update(['actif' => false]);
            }
        });
    }

    public function employe(): BelongsTo
    {
        return $this->belongsTo(Employe::class);
    }

    public function totalElementsFixes(): int
    {
        return (int) collect($this->elements_fixes ?? [])->sum(fn ($e) => (int) ($e['montant'] ?? 0));
    }

    /** Jours restants avant la fin du contrat (null si sans fin). */
    public function joursRestants(): ?int
    {
        return $this->date_fin ? Vehicule::jours(today(), $this->date_fin) : null;
    }
}
