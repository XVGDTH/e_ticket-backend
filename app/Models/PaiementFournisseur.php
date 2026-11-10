<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaiementFournisseur extends Model
{
    protected $table = 'ci_paiements_fournisseurs';

    protected $fillable = [
        'compagnie_id',
        'facture_id',
        'numero',
        'montant',
        'paye_le',
        'mode',
        'reference',
        'statut',
        'cree_par',
        'cree_par_nom',
        'valide_par',
        'valide_par_nom',
        'valide_le',
    ];

    protected function casts(): array
    {
        return [
            'montant' => 'integer',
            'paye_le' => 'date',
            'valide_le' => 'datetime',
        ];
    }

    public function compagnie(): BelongsTo
    {
        return $this->belongsTo(Compagnie::class);
    }

    public function facture(): BelongsTo
    {
        return $this->belongsTo(FactureFournisseur::class, 'facture_id');
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par');
    }

    public function validateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'valide_par');
    }
}
