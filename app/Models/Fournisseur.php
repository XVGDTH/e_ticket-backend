<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Fournisseur extends Model
{
    protected $table = 'ci_fournisseurs';

    protected $fillable = [
        'compagnie_id',
        'nom',
        'identifiant_fiscal',
        'telephone',
        'email',
        'adresse',
        'actif',
        'cree_par',
        'cree_par_nom',
    ];

    protected function casts(): array
    {
        return ['actif' => 'boolean'];
    }

    public function compagnie(): BelongsTo
    {
        return $this->belongsTo(Compagnie::class);
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par');
    }

    public function receptions(): HasMany
    {
        return $this->hasMany(ReceptionFournisseur::class, 'fournisseur_id');
    }

    public function factures(): HasMany
    {
        return $this->hasMany(FactureFournisseur::class, 'fournisseur_id');
    }
}
