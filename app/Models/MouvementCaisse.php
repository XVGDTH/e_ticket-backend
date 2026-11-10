<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MouvementCaisse extends Model
{
    protected $table = 'mouvements_caisse';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = ['created_at' => 'datetime', 'montant' => 'integer'];

    public const TYPES = [
        'vente' => 'Vente', 'annulation' => 'Annulation', 'remboursement' => 'Remboursement',
        'entree' => 'Entrée de fonds', 'sortie' => 'Sortie de fonds',
    ];

    public function caisse(): BelongsTo
    {
        return $this->belongsTo(Caisse::class);
    }

    public function billet(): BelongsTo
    {
        return $this->belongsTo(Billet::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeVisiblePour(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        if ($user->isAdminCompagnie()) {
            $query->where('compagnie_id', $user->compagnieId() ?? 0);

            return $user->villeId() ? $query->whereIn('gare_id', $user->garesGerables()->select('gares.id')) : $query;
        }

        if ($user->isAgentGare()) {
            return $query->where('user_id', $user->id);
        }

        return $query->whereRaw('1 = 0');
    }
}
