<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\AppartientAUneCompagnie;

class Gare extends Model
{
    use HasFactory, AppartientAUneCompagnie;


    protected $fillable = [
        'compagnie_id', 'ville_id', 'quartier',
        'latitude', 'longitude', 'telephone', 'adresse',
    ];

    public function compagnie(): BelongsTo
    {
        return $this->belongsTo(Compagnie::class);
    }

    public function ville(): BelongsTo
    {
        return $this->belongsTo(Ville::class);
    }

    public function routesDepart(): HasMany
    {
        return $this->hasMany(RouteTransport::class, 'gare_depart_id');
    }
}