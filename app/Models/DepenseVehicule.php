<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DepenseVehicule extends Model
{
    protected $table = 'depenses_vehicules';

    protected $guarded = [];

    protected $casts = [
        'date_depense' => 'date',
        'couvre_du' => 'date',
        'couvre_au' => 'date',
        'montant' => 'integer',
        'litres' => 'float',
        'kilometrage' => 'integer',
    ];

    public const CATEGORIES = [
        'carburant' => 'Carburant',
        'entretien' => 'Entretien courant (vidange, filtres)',
        'reparation' => 'Réparation',
        'pneus' => 'Pneus',
        'peage' => 'Péages et frais de route',
        'chauffeur' => 'Chauffeur (salaire, prime, indemnité)',
        'assurance' => 'Assurance',
        'visite_technique' => 'Visite technique',
        'taxes' => 'Taxes et vignettes',
        'amende' => 'Amendes',
        'lavage' => 'Lavage et nettoyage',
        'autre' => 'Autre',
    ];

    /** Catégories que peut saisir un agent de gare. */
    public const CATEGORIES_AGENT = ['carburant', 'peage', 'lavage', 'autre'];

    /** Catégories dont le montant couvre souvent une période (étalée dans le calcul). */
    public const CATEGORIES_PERIODE = ['assurance', 'visite_technique', 'taxes'];

    public const GROUPES = [
        'carburant' => 'Carburant',
        'entretien' => 'Entretien et réparations',
        'route' => 'Péages et frais de route',
        'chauffeur' => 'Chauffeur',
        'fixes' => 'Assurance, visite et taxes',
        'autres' => 'Amendes et autres',
    ];

    public static function groupe(string $categorie): string
    {
        return match ($categorie) {
            'carburant' => 'carburant',
            'entretien', 'reparation', 'pneus', 'lavage' => 'entretien',
            'peage' => 'route',
            'chauffeur' => 'chauffeur',
            'assurance', 'visite_technique', 'taxes' => 'fixes',
            default => 'autres',
        };
    }

    public function vehicule(): BelongsTo
    {
        return $this->belongsTo(Vehicule::class, 'vehicule_id');
    }

    public function voyage(): BelongsTo
    {
        return $this->belongsTo(VoyageCar::class, 'voyage_id');
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par');
    }

    /** Dépenses des cars visibles par l'utilisateur, plus celles qu'il a lui-même saisies. */
    public function scopeVisiblePour(Builder $query, User $user): Builder
    {
        return $query->where(fn ($q) => $q
            ->whereIn('vehicule_id', Vehicule::query()->visiblePour($user)->select('vehicules.id'))
            ->orWhere('cree_par', $user->id));
    }
}
