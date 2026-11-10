<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FactureFournisseur extends Model
{
    protected $table = 'ci_factures_fournisseurs';

    protected $fillable = [
        'compagnie_id',
        'fournisseur_id',
        'reception_id',
        'numero',
        'reference_fournisseur',
        'date_facture',
        'echeance',
        'montant',
        'piece_path',
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
            'date_facture' => 'date',
            'echeance' => 'date',
            'valide_le' => 'datetime',
            'montant' => 'integer',
        ];
    }

    public function compagnie(): BelongsTo
    {
        return $this->belongsTo(Compagnie::class);
    }

    public function fournisseur(): BelongsTo
    {
        return $this->belongsTo(Fournisseur::class);
    }

    public function reception(): BelongsTo
    {
        return $this->belongsTo(ReceptionFournisseur::class, 'reception_id');
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par');
    }

    public function paiements(): HasMany
    {
        return $this->hasMany(PaiementFournisseur::class, 'facture_id');
    }
}
