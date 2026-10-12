<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Compagnie extends Model
{
    use HasFactory;

    protected $fillable = ['nom', 'slug', 'logo_url', 'actif'];

    protected $casts = ['actif' => 'boolean'];

    public function gares(): HasMany
    {
        return $this->hasMany(Gare::class);
    }

    public function routes(): HasMany
    {
        return $this->hasMany(RouteTransport::class);
    }
}