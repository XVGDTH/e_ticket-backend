<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ville extends Model
{
    use HasFactory;

    protected $fillable = ['nom', 'pays', 'etranger', 'aliases'];

    protected $casts = ['aliases' => 'array', 'etranger' => 'boolean'];

    protected static function booted(): void
    {
        // Le mot-clé « étranger » découle du pays : tout pays autre que le Burkina Faso.
        static::saving(function (Ville $ville) {
            $pays = trim((string) ($ville->pays ?: 'Burkina Faso'));

            $ville->pays = $pays;
            $ville->etranger = mb_strtolower($pays) !== 'burkina faso';
        });
    }

    public function gares(): HasMany
    {
        return $this->hasMany(Gare::class);
    }
}
