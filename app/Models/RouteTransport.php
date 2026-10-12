<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\AppartientAUneCompagnie;

class RouteTransport extends Model
{
    use HasFactory, AppartientAUneCompagnie;
    protected $fillable = [
        'compagnie_id', 'gare_depart_id', 'ville_arrivee_id',
        'prix', 'duree_max', 'actif',
    ];

    protected $casts = ['actif' => 'boolean'];

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
}