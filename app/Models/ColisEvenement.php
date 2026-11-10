<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ColisEvenement extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'colis_evenements';

    protected $fillable = ['colis_id', 'statut', 'user_id', 'gare_id', 'note', 'created_at'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function gare(): BelongsTo
    {
        return $this->belongsTo(Gare::class);
    }
}
