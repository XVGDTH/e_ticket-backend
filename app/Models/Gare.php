<?php

namespace App\Models;

use App\Models\Concerns\AppartientAUneCompagnie;
use App\Models\Concerns\JournaliseParametres;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Gare extends Model
{
    use AppartientAUneCompagnie, HasFactory, JournaliseParametres;

    protected $fillable = [
        'compagnie_id', 'ville_id', 'quartier',
        'latitude', 'longitude', 'telephone', 'adresse',
    ];

    protected function champsJournalises(): array
    {
        return [
            'quota_app_pourcent' => 'Quota de l’application (%)',
        ];
    }

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
