<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ville extends Model
{
    use HasFactory;

    protected $fillable = ['nom', 'aliases'];

    protected $casts = ['aliases' => 'array'];

    public function gares(): HasMany
    {
        return $this->hasMany(Gare::class);
    }
}