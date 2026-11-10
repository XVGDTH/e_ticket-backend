<?php

namespace App\Models;

use Database\Factories\CptCompteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CptCompte extends Model
{
    /** @use HasFactory<CptCompteFactory> */
    use HasFactory;

    protected $table = 'cpt_comptes';

    protected $guarded = [];

    protected $casts = ['actif' => 'boolean'];

    public function compagnie(): BelongsTo
    {
        return $this->belongsTo(Compagnie::class);
    }
}
