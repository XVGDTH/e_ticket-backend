<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VerificationBillet extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'verifications_billets';

    protected $fillable = ['billet_id', 'user_id', 'gare_id', 'mode', 'resultat', 'contenu'];

    public function billet(): BelongsTo
    {
        return $this->belongsTo(Billet::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function gare(): BelongsTo
    {
        return $this->belongsTo(Gare::class);
    }

    /** Le super admin voit tout ; l'admin de compagnie, ses gares ; l'agent, ses propres contrôles. */
    public function scopeVisiblePour(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        if ($user->isAdminCompagnie()) {
            return $query->whereIn('gare_id', $user->garesGerables()->select('gares.id'));
        }

        if ($user->isAgentGare()) {
            return $query->where('user_id', $user->id);
        }

        return $query->whereRaw('1 = 0');
    }
}
