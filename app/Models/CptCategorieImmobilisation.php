<?php

namespace App\Models;

use Database\Factories\CptCategorieImmobilisationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CptCategorieImmobilisation extends Model
{
    /** @use HasFactory<CptCategorieImmobilisationFactory> */
    use HasFactory;

    protected $table = 'cpt_categories_immobilisations';

    protected $fillable = [
        'compagnie_id',
        'code',
        'libelle',
        'duree_utilite_mois',
        'mode_amortissement',
        'premiere_periode',
        'compte_actif_id',
        'compte_amortissement_id',
        'compte_charge_id',
        'actif',
        'cree_par',
    ];

    protected function casts(): array
    {
        return [
            'duree_utilite_mois' => 'integer',
            'actif' => 'boolean',
        ];
    }

    public function compagnie(): BelongsTo
    {
        return $this->belongsTo(Compagnie::class);
    }

    public function compteActif(): BelongsTo
    {
        return $this->belongsTo(CptCompte::class, 'compte_actif_id');
    }

    public function compteAmortissement(): BelongsTo
    {
        return $this->belongsTo(CptCompte::class, 'compte_amortissement_id');
    }

    public function compteCharge(): BelongsTo
    {
        return $this->belongsTo(CptCompte::class, 'compte_charge_id');
    }

    public function immobilisations(): HasMany
    {
        return $this->hasMany(CptImmobilisation::class, 'categorie_id');
    }
}
