<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AvanceEmploye extends Model
{
    protected $table = 'avances_employes';

    protected $guarded = [];

    protected $casts = [
        'date_octroi' => 'date',
        'debut_remboursement' => 'date',
        'montant' => 'integer',
        'mensualite' => 'integer',
        'reste_du' => 'integer',
    ];

    public const TYPES = ['avance' => 'Avance sur salaire', 'pret' => 'Prêt'];

    public const STATUTS = ['en_cours' => 'En cours', 'solde' => 'Soldée', 'annule' => 'Annulée'];

    protected static function booted(): void
    {
        static::creating(function (AvanceEmploye $a) {
            $a->cree_par ??= auth()->id();
            $a->compagnie_id = Employe::whereKey($a->employe_id)->value('compagnie_id');
            $a->nb_mensualites = max(1, (int) $a->nb_mensualites);
            $a->mensualite = (int) ceil($a->montant / $a->nb_mensualites);
            $a->reste_du = $a->montant;
            $a->debut_remboursement ??= $a->date_octroi->copy()->addMonthNoOverflow()->startOfMonth();
            $a->statut = 'en_cours';
        });
    }

    public function employe(): BelongsTo
    {
        return $this->belongsTo(Employe::class);
    }

    public function remboursements(): HasMany
    {
        return $this->hasMany(RemboursementAvance::class, 'avance_id');
    }
}
