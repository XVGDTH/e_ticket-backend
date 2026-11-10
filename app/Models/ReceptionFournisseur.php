<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReceptionFournisseur extends Model
{
    protected $table = 'ci_receptions_fournisseurs';

    protected $fillable = [
        'compagnie_id',
        'fournisseur_id',
        'numero',
        'recu_le',
        'description',
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
            'recu_le' => 'date',
            'valide_le' => 'datetime',
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

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par');
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(LigneReceptionFournisseur::class, 'reception_id');
    }

    public function factures(): HasMany
    {
        return $this->hasMany(FactureFournisseur::class, 'reception_id');
    }
}
