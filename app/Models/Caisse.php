<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Caisse extends Model
{
    protected $table = 'caisses';

    protected $guarded = [];

    protected $casts = [
        'ouverte_le' => 'datetime', 'fermee_le' => 'datetime', 'valide_le' => 'datetime',
        'fond_initial' => 'integer', 'especes_attendues' => 'integer', 'especes_comptees' => 'integer', 'ecart' => 'integer',
    ];

    public const STATUTS = ['ouverte' => 'Ouverte', 'fermee' => 'Fermée', 'validee' => 'Validée'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function gare(): BelongsTo
    {
        return $this->belongsTo(Gare::class);
    }

    public function mouvements(): HasMany
    {
        return $this->hasMany(MouvementCaisse::class);
    }

    public function libelleAgent(): string
    {
        return trim(($this->user?->prenom ?? '').' '.($this->user?->nom ?? '')) ?: '—';
    }

    /** Espèces qui doivent se trouver dans le tiroir : fond de caisse + mouvements en espèces. */
    public function especesAttendues(): int
    {
        return (int) $this->fond_initial + (int) $this->mouvements()->where('mode', 'especes')->sum('montant');
    }

    /** @return array<string,int> net encaissé par mode de paiement */
    public function totauxParMode(): array
    {
        return $this->mouvements()->whereIn('type', ['vente', 'annulation', 'remboursement'])
            ->selectRaw('mode, SUM(montant) as total')->groupBy('mode')->pluck('total', 'mode')
            ->map(fn ($v) => (int) $v)->all();
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
