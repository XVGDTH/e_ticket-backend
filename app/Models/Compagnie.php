<?php

namespace App\Models;

use App\Models\Concerns\JournaliseParametres;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Compagnie extends Model
{
    use HasFactory, JournaliseParametres;

    // protected $fillable = ['nom', 'slug', 'logo_url', 'actif'];

    // protected $casts = ['actif' => 'boolean'];
    protected $fillable = ['nom', 'slug', 'logo_url', 'actif', 'places_par_depart', 'quota_app_pourcent',
        'validite_jours',

    ];

    protected $casts = [
        'actif' => 'boolean',
        'places_par_depart' => 'integer',
        'quota_app_pourcent' => 'integer',
        'validite_jours' => 'integer',
    ];

    protected function champsJournalises(): array
    {
        return [
            'places_par_depart' => 'Places par départ',
            'quota_app_pourcent' => 'Quota de l’application (%)',
            'validite_jours' => 'Validité du billet (jours)',
        ];
    }

    public function gares(): HasMany
    {
        return $this->hasMany(Gare::class);
    }

    public function routes(): HasMany
    {
        return $this->hasMany(RouteTransport::class);
    }

    /** Adresse affichable du logo : chemin du disque public ou adresse déjà complète. */
    public function getLogoAffichableAttribute(): ?string
    {
        $logo = $this->logo_url;

        if (blank($logo)) {
            return null;
        }

        if (str_starts_with($logo, 'http://') || str_starts_with($logo, 'https://') || str_starts_with($logo, '/')) {
            return $logo;
        }

        return asset('storage/'.ltrim($logo, '/'));
    }
}
