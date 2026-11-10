<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SanctionEmploye extends Model
{
    protected $table = 'sanctions_employes';

    protected $guarded = [];

    protected $casts = ['date_faits' => 'date', 'date_sanction' => 'date', 'jours_mise_a_pied' => 'integer'];

    public const TYPES = [
        'avertissement_verbal' => 'Avertissement verbal',
        'avertissement_ecrit' => 'Avertissement écrit',
        'blame' => 'Blâme',
        'mise_a_pied' => 'Mise à pied',
        'licenciement' => 'Licenciement',
    ];

    protected static function booted(): void
    {
        static::creating(function (SanctionEmploye $s) {
            $s->cree_par ??= auth()->id();
            $s->compagnie_id = Employe::whereKey($s->employe_id)->value('compagnie_id');
        });
    }

    public function employe(): BelongsTo
    {
        return $this->belongsTo(Employe::class);
    }
}
