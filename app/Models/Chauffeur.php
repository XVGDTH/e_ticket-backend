<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Chauffeur extends Model
{
    protected $table = 'chauffeurs';

    protected $guarded = [];

    protected $casts = [
        'permis_expire_le' => 'date',
        'date_embauche' => 'date',
        'actif' => 'boolean',
        'remuneration_montant' => 'integer',
    ];

    public function compagnie(): BelongsTo
    {
        return $this->belongsTo(Compagnie::class);
    }

    public function gare(): BelongsTo
    {
        return $this->belongsTo(Gare::class);
    }

    public function vehiculeHabituel(): BelongsTo
    {
        return $this->belongsTo(Vehicule::class, 'vehicule_habituel_id');
    }

    public function nomComplet(): string
    {
        return trim($this->prenom.' '.$this->nom);
    }

    public function permisEtat(): string
    {
        if (! $this->permis_expire_le) {
            return 'Non renseigné';
        }

        $jours = Vehicule::jours(today(), $this->permis_expire_le);

        return $jours < 0 ? 'Expiré' : ($jours <= (int) config('flotte.alerte_jours', 30) ? 'À renouveler' : 'Valide');
    }

    public function scopeVisiblePour(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        if ($user->isAdminCompagnie() || $user->roleDonneAccesAuModule('flotte')) {
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
}
