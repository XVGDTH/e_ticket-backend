<?php

namespace App\Models;

use App\Models\Concerns\AppartientAUneCompagnie;
use App\Models\Concerns\JournaliseParametres;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RouteTransport extends Model
{
    use AppartientAUneCompagnie, HasFactory, JournaliseParametres;

    protected $fillable = [
        'compagnie_id', 'gare_depart_id', 'ville_arrivee_id',
        'prix', 'duree_max', 'actif', 'distance_km',
    ];

    protected $casts = [
        'actif' => 'boolean',
        'distance_km' => 'integer',
    ];

    protected function champsJournalises(): array
    {
        return [
            'prix' => 'Tarif du trajet',
        ];
    }

    public function compagnie(): BelongsTo
    {
        return $this->belongsTo(Compagnie::class);
    }

    public function gareDepart(): BelongsTo
    {
        return $this->belongsTo(Gare::class, 'gare_depart_id');
    }

    public function villeArrivee(): BelongsTo
    {
        return $this->belongsTo(Ville::class, 'ville_arrivee_id');
    }

    public function horaires(): HasMany
    {
        return $this->hasMany(Horaire::class);
    }

    public function billets(): HasMany
    {
        return $this->hasMany(Billet::class, 'route_transport_id');
    }

    /** Sépare les trajets nationaux des trajets internationaux (départ ou arrivée dans une ville étrangère). */
    public function scopeDuType(Builder $query, string $type): Builder
    {
        $etrangers = Ville::where('etranger', true)->pluck('id');

        if ($type === 'international') {
            return $query->where(
                fn ($q) => $q->whereIn('ville_arrivee_id', $etrangers)
                    ->orWhereHas('gareDepart', fn ($g) => $g->whereIn('ville_id', $etrangers))
            );
        }

        return $query->whereNotIn('ville_arrivee_id', $etrangers)
            ->whereDoesntHave('gareDepart', fn ($g) => $g->whereIn('ville_id', $etrangers));
    }

    /** Vrai si le départ ou l'arrivée est dans une ville étrangère. */
    public function estInternationale(): bool
    {
        return (bool) ($this->villeArrivee?->etranger || $this->gareDepart?->ville?->etranger);
    }
}
