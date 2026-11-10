<?php

namespace App\Models;

use App\Models\Concerns\JournaliseParametres;
use App\Services\Frais;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FraisRegle extends Model
{
    use JournaliseParametres;

    protected $table = 'frais_regles';

    protected $fillable = [
        'compagnie_id', 'gare_id', 'route_transport_id', 'trajet', 'prix_min', 'prix_max',
        'mode', 'valeur', 'frais_min', 'frais_max', 'actif', 'note',
    ];

    protected $casts = [
        'actif' => 'boolean',
        'prix_min' => 'integer',
        'prix_max' => 'integer',
        'valeur' => 'float',
        'frais_min' => 'integer',
        'frais_max' => 'integer',
    ];

    protected function champsJournalises(): array
    {
        return [
            'compagnie_id' => 'Compagnie',
            'gare_id' => 'Gare',
            'route_transport_id' => 'Route',
            'trajet' => 'Type de trajet',
            'prix_min' => 'Prix minimum du billet',
            'prix_max' => 'Prix maximum du billet',
            'mode' => 'Mode de calcul',
            'valeur' => 'Valeur du tarif',
            'frais_min' => 'Frais minimum',
            'frais_max' => 'Frais maximum',
            'actif' => 'Tarif actif',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Frais::oublier());
        static::deleted(fn () => Frais::oublier());
    }

    public function compagnie(): BelongsTo
    {
        return $this->belongsTo(Compagnie::class);
    }

    public function gare(): BelongsTo
    {
        return $this->belongsTo(Gare::class);
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(RouteTransport::class, 'route_transport_id');
    }

    /** Plus la clé est grande, plus la règle est précise : portée, type de trajet, tranche la plus étroite. */
    public function cle(): array
    {
        return [
            ($this->route_transport_id ? 8 : 0) + ($this->gare_id ? 4 : 0) + ($this->compagnie_id ? 2 : 0),
            $this->trajet ? 1 : 0,
            (int) $this->prix_min,
            -($this->prix_max ?? PHP_INT_MAX),
            (int) $this->id,
        ];
    }

    public function montantPour(int $prixUnitaire, int $voyageurs): int
    {
        $montant = $this->mode === 'pourcentage'
            ? (int) round($prixUnitaire * max(1, $voyageurs) * $this->valeur / 100)
            : (int) round($this->valeur);

        if ($this->frais_min !== null) {
            $montant = max($montant, $this->frais_min);
        }

        if ($this->frais_max !== null) {
            $montant = min($montant, $this->frais_max);
        }

        return max(0, $montant);
    }

    public function libellePortee(): string
    {
        return match (true) {
            $this->route_transport_id !== null => 'Route précise',
            $this->gare_id !== null => 'Gare',
            $this->compagnie_id !== null => 'Compagnie',
            default => 'Toutes les compagnies',
        };
    }

    public function libelleDetail(): string
    {
        return collect([$this->compagnie?->nom, $this->gare?->quartier])->filter()->implode(' · ') ?: 'Tarif général';
    }

    public function libelleTranche(): string
    {
        $fmt = fn (int $n) => number_format($n, 0, ',', ' ');

        if ($this->prix_max === null) {
            return $this->prix_min > 0 ? 'À partir de '.$fmt($this->prix_min).' FCFA' : 'Tous les prix';
        }

        return $fmt($this->prix_min).' à '.$fmt($this->prix_max).' FCFA';
    }

    public function libelleFrais(): string
    {
        $fmt = fn ($n) => number_format((float) $n, 0, ',', ' ');

        if ($this->mode !== 'pourcentage') {
            return $fmt($this->valeur).' FCFA';
        }

        $texte = rtrim(rtrim(number_format($this->valeur, 2, ',', ' '), '0'), ',').' %';

        if ($this->frais_min !== null) {
            $texte .= ', min '.$fmt($this->frais_min);
        }

        if ($this->frais_max !== null) {
            $texte .= ', max '.$fmt($this->frais_max);
        }

        return $texte;
    }
}
