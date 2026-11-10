<?php

namespace App\Models;

use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class CongeEmploye extends Model
{
    protected $table = 'conges_employes';

    protected $guarded = [];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date',
        'decide_le' => 'datetime',
        'jours' => 'integer',
    ];

    public const TYPES = [
        'annuel' => 'Congé annuel payé',
        'maladie' => 'Maladie (certificat)',
        'maternite' => 'Maternité',
        'permission' => 'Permission exceptionnelle (naissance, décès, mariage…)',
        'sans_solde' => 'Sans solde',
        'autre' => 'Autre',
    ];

    public const STATUTS = ['demande' => 'En attente', 'approuve' => 'Approuvé', 'refuse' => 'Refusé', 'annule' => 'Annulé'];

    /** Statut de présence posé automatiquement sur chaque jour d'un congé approuvé. */
    public const STATUT_PRESENCE = [
        'annuel' => 'conge', 'maladie' => 'maladie', 'maternite' => 'conge',
        'permission' => 'permission', 'sans_solde' => 'conge_sans_solde', 'autre' => 'permission',
    ];

    protected static function booted(): void
    {
        static::creating(function (CongeEmploye $c) {
            $c->cree_par ??= auth()->id();
            $c->compagnie_id = Employe::whereKey($c->employe_id)->value('compagnie_id');
        });

        static::saving(function (CongeEmploye $c) {
            $c->jours = (int) $c->date_debut->diffInDays($c->date_fin) + 1;
        });
    }

    public function employe(): BelongsTo
    {
        return $this->belongsTo(Employe::class);
    }

    public function scopeVisiblePour(Builder $query, User $user): Builder
    {
        return $query->whereHas('employe', fn ($q) => $q->visiblePour($user));
    }

    /** @throws \DomainException */
    public function approuver(User $acteur): void
    {
        DB::transaction(function () use ($acteur) {
            $c = static::query()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();

            if ($c->statut !== 'demande') {
                throw new \DomainException('Cette demande a déjà été traitée.');
            }

            $c->forceFill(['statut' => 'approuve', 'decide_par' => $acteur->id, 'decide_le' => now()])->save();

            $statut = self::STATUT_PRESENCE[$c->type] ?? 'permission';

            foreach (CarbonPeriod::create($c->date_debut, $c->date_fin) as $jour) {
                Presence::updateOrCreate(
                    ['employe_id' => $c->employe_id, 'date' => $jour->toDateString()],
                    ['statut' => $statut, 'conge_id' => $c->id, 'heure_arrivee' => null, 'heure_depart' => null,
                        'minutes_retard' => 0, 'note' => 'Congé #'.$c->id]
                );
            }
        });
    }

    /** @throws \DomainException */
    public function refuser(User $acteur, ?string $motif): void
    {
        if ($this->statut !== 'demande') {
            throw new \DomainException('Cette demande a déjà été traitée.');
        }

        $this->forceFill([
            'statut' => 'refuse', 'decide_par' => $acteur->id, 'decide_le' => now(), 'motif_refus' => $motif,
        ])->save();
    }

    /** Annule un congé approuvé : les jours sont rendus au pointage. */
    public function annuler(User $acteur): void
    {
        DB::transaction(function () use ($acteur) {
            if (! in_array($this->statut, ['demande', 'approuve'], true)) {
                throw new \DomainException('Ce congé ne peut plus être annulé.');
            }

            Presence::where('conge_id', $this->id)->delete();
            $this->forceFill(['statut' => 'annule', 'decide_par' => $acteur->id, 'decide_le' => now()])->save();
        });
    }
}
