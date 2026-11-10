<?php

namespace App\Models;

use Database\Factories\CptImmobilisationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CptImmobilisation extends Model
{
    /** @use HasFactory<CptImmobilisationFactory> */
    use HasFactory;

    protected $table = 'cpt_immobilisations';

    protected $fillable = [
        'compagnie_id',
        'categorie_id',
        'code',
        'designation',
        'description',
        'date_acquisition',
        'date_mise_en_service',
        'cout_acquisition',
        'valeur_residuelle',
        'duree_utilite_mois',
        'mode_amortissement',
        'premiere_periode',
        'compte_actif_id',
        'compte_amortissement_id',
        'compte_charge_id',
        'statut',
        'cree_par',
        'valide_par',
        'validee_le',
    ];

    protected function casts(): array
    {
        return [
            'date_acquisition' => 'date',
            'date_mise_en_service' => 'date',
            'cout_acquisition' => 'integer',
            'valeur_residuelle' => 'integer',
            'duree_utilite_mois' => 'integer',
            'validee_le' => 'datetime',
        ];
    }

    public function compagnie(): BelongsTo
    {
        return $this->belongsTo(Compagnie::class);
    }

    public function categorie(): BelongsTo
    {
        return $this->belongsTo(CptCategorieImmobilisation::class, 'categorie_id');
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

    public function amortissements(): HasMany
    {
        return $this->hasMany(CptAmortissement::class, 'immobilisation_id')->orderBy('periode_fin');
    }
}
