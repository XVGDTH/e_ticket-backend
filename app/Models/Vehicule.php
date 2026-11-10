<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicule extends Model
{
    protected $table = 'vehicules';

    protected $guarded = [];

    protected $casts = [
        'date_acquisition' => 'date',
        'date_mise_en_service' => 'date',
        'assurance_expire_le' => 'date',
        'visite_technique_expire_le' => 'date',
        'autre_doc_expire_le' => 'date',
        'prix_acquisition' => 'integer',
        'valeur_residuelle' => 'integer',
        'duree_amortissement_ans' => 'integer',
        'places' => 'integer',
        'km_actuel' => 'integer',
        'annee' => 'integer',
    ];

    public const STATUTS = [
        'actif' => 'En service',
        'maintenance' => 'En maintenance',
        'panne' => 'En panne',
        'reforme' => 'Réformé',
    ];

    public const CARBURANTS = ['gasoil' => 'Gasoil', 'essence' => 'Essence', 'autre' => 'Autre'];

    /** Nombre de jours entre deux dates (positif si $b est après $a). */
    public static function jours(Carbon $a, Carbon $b): int
    {
        return (int) round(($b->copy()->startOfDay()->getTimestamp() - $a->copy()->startOfDay()->getTimestamp()) / 86400);
    }

    public function compagnie(): BelongsTo
    {
        return $this->belongsTo(Compagnie::class);
    }

    public function gare(): BelongsTo
    {
        return $this->belongsTo(Gare::class);
    }

    public function voyages(): HasMany
    {
        return $this->hasMany(VoyageCar::class, 'vehicule_id');
    }

    public function depenses(): HasMany
    {
        return $this->hasMany(DepenseVehicule::class, 'vehicule_id');
    }

    /** Le super admin voit tout ; l'admin, sa compagnie (et ses gares s'il a une ville) ; l'agent, les cars de sa gare. */
    public function scopeVisiblePour(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        if ($user->isAdminCompagnie()) {
            $query->where('compagnie_id', $user->compagnieId() ?? 0);

            if ($user->villeId()) {
                $query->whereIn('gare_id', $user->garesGerables()->select('gares.id'));
            }

            return $query;
        }

        if ($user->isAgentGare()) {
            return $query->where('gare_id', $user->gareId() ?? 0);
        }

        return $query->whereRaw('1 = 0');
    }

    public function libelle(): string
    {
        return $this->immatriculation.($this->marque ? ' · '.trim($this->marque.' '.$this->modele) : '');
    }

    public function ageAns(): ?int
    {
        if ($this->annee) {
            return max(0, now()->year - (int) $this->annee);
        }

        $d = $this->debutAmortissement();

        return $d ? max(0, (int) floor(self::jours($d, today()) / 365.25)) : null;
    }

    // ------------------------------------------------------------------ amortissement

    public function debutAmortissement(): ?Carbon
    {
        return $this->date_mise_en_service ?? $this->date_acquisition;
    }

    public function estAmortissable(): bool
    {
        return (int) $this->prix_acquisition > 0
            && (int) $this->duree_amortissement_ans > 0
            && $this->debutAmortissement() !== null;
    }

    public function finAmortissement(): ?Carbon
    {
        $debut = $this->debutAmortissement();

        return $debut && $this->duree_amortissement_ans
            ? $debut->copy()->addYears((int) $this->duree_amortissement_ans)->subDay()
            : null;
    }

    public function baseAmortissable(): int
    {
        return max(0, (int) $this->prix_acquisition - (int) $this->valeur_residuelle);
    }

    /** Amortissement linéaire au jour le jour sur la durée saisie, entre deux dates. */
    public function amortissementPour(Carbon $du, Carbon $au): int
    {
        if (! $this->estAmortissable()) {
            return 0;
        }

        $debut = $this->debutAmortissement();
        $fin = $this->finAmortissement();
        $a = $du->gt($debut) ? $du : $debut;
        $b = $au->lt($fin) ? $au : $fin;

        if ($a->gt($b)) {
            return 0;
        }

        $total = self::jours($debut, $fin) + 1;

        return (int) round($this->baseAmortissable() * (self::jours($a, $b) + 1) / $total);
    }

    public function amortissementCumule(Carbon $au): int
    {
        return $this->estAmortissable() ? $this->amortissementPour($this->debutAmortissement(), $au) : 0;
    }

    public function valeurNetteComptable(Carbon $au): int
    {
        return max(0, (int) $this->prix_acquisition - $this->amortissementCumule($au));
    }

    // ------------------------------------------------------------------ documents

    /** @return array<int, array{libelle:string, date:Carbon, jours:int}> */
    public function documents(): array
    {
        return collect([
            ['Assurance', $this->assurance_expire_le],
            ['Visite technique', $this->visite_technique_expire_le],
            [$this->autre_doc_libelle ?: 'Autre document', $this->autre_doc_expire_le],
        ])
            ->filter(fn ($d) => $d[1] !== null)
            ->map(fn ($d) => ['libelle' => $d[0], 'date' => $d[1], 'jours' => self::jours(today(), $d[1])])
            ->values()
            ->all();
    }

    public function etatDocuments(): string
    {
        $docs = $this->documents();

        if ($docs === []) {
            return 'Non renseignés';
        }

        $min = min(array_column($docs, 'jours'));

        return $min < 0 ? 'Expirés' : ($min <= (int) config('flotte.alerte_jours', 30) ? 'À renouveler' : 'À jour');
    }
}
