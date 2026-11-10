<?php

namespace App\Models;

use App\Models\Concerns\JournaliseParametres;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChargeStructure extends Model
{
    use JournaliseParametres;

    protected $table = 'cg_charges_structure';

    protected $guarded = [];

    protected $casts = [
        'periode_debut' => 'date',
        'periode_fin' => 'date',
        'montant' => 'integer',
    ];

    public const NIVEAUX = [
        'gare' => 'Gare ou agence',
        'siege' => 'Siège',
        'atelier' => 'Atelier',
    ];

    protected function champsJournalises(): array
    {
        return [
            'niveau' => 'Niveau',
            'gare_id' => 'Gare',
            'montant' => 'Montant',
            'periode_debut' => 'Début de période',
            'periode_fin' => 'Fin de période',
            'cle' => 'Clé de répartition',
        ];
    }

    public function compagnie(): BelongsTo
    {
        return $this->belongsTo(Compagnie::class);
    }

    public function gare(): BelongsTo
    {
        return $this->belongsTo(Gare::class);
    }

    /** Part du montant qui tombe dans la fenêtre analysée, étalée jour par jour sur la période de la charge. */
    public function partPour(Carbon $debut, Carbon $fin): float
    {
        $total = Vehicule::jours($this->periode_debut, $this->periode_fin) + 1;

        if ($total <= 0) {
            return 0.0;
        }

        $a = $this->periode_debut->gt($debut) ? $this->periode_debut : $debut;
        $b = $this->periode_fin->lt($fin) ? $this->periode_fin : $fin;

        return $a->gt($b) ? 0.0 : $this->montant * (Vehicule::jours($a, $b) + 1) / $total;
    }

    public function scopeVisiblePour(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        if ($user->isAdminCompagnie()) {
            $query->where('compagnie_id', $user->compagnieId() ?? 0);

            return $user->villeId()
                ? $query->whereIn('gare_id', $user->garesGerables()->select('gares.id'))
                : $query;
        }

        return $query->whereRaw('1 = 0');
    }
}
