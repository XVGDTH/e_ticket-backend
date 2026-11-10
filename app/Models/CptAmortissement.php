<?php

namespace App\Models;

use Database\Factories\CptAmortissementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CptAmortissement extends Model
{
    /** @use HasFactory<CptAmortissementFactory> */
    use HasFactory;

    protected $table = 'cpt_amortissements';

    protected $fillable = [
        'compagnie_id',
        'immobilisation_id',
        'periode_debut',
        'periode_fin',
        'montant',
        'statut',
        'ecriture_id',
        'cree_par',
        'valide_par',
        'validee_le',
    ];

    protected function casts(): array
    {
        return [
            'periode_debut' => 'date',
            'periode_fin' => 'date',
            'montant' => 'integer',
            'validee_le' => 'datetime',
        ];
    }

    public function compagnie(): BelongsTo
    {
        return $this->belongsTo(Compagnie::class);
    }

    public function immobilisation(): BelongsTo
    {
        return $this->belongsTo(CptImmobilisation::class, 'immobilisation_id');
    }

    public function ecriture(): BelongsTo
    {
        return $this->belongsTo(CptEcriture::class, 'ecriture_id');
    }

    public function creePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par');
    }

    public function validePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'valide_par');
    }
}
