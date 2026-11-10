<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BulletinPaie extends Model
{
    protected $table = 'bulletins_paie';

    protected $guarded = [];

    protected $casts = ['detail' => 'array', 'paye_le' => 'datetime'];

    public function periode(): BelongsTo
    {
        return $this->belongsTo(PeriodePaie::class, 'periode_paie_id');
    }

    public function employe(): BelongsTo
    {
        return $this->belongsTo(Employe::class);
    }

    public function contrat(): BelongsTo
    {
        return $this->belongsTo(ContratEmploye::class, 'contrat_id');
    }

    public function compagnie(): BelongsTo
    {
        return $this->belongsTo(Compagnie::class);
    }

    public function scopeVisiblePour(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        if ($user->isAdminCompagnie()) {
            $query->where('compagnie_id', $user->compagnieId() ?? 0);

            if ($user->villeId()) {
                $query->whereHas('employe', fn ($q) => $q->visiblePour($user));
            }

            return $query;
        }

        return $query->whereRaw('1 = 0');
    }
}
