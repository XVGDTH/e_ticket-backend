<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PreferenceClient extends Model
{
    protected $table = 'preferences_clients';

    protected $guarded = [];

    protected $casts = ['premier_achat' => 'date', 'dernier_achat' => 'date', 'score' => 'float'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function compagnie(): BelongsTo
    {
        return $this->belongsTo(Compagnie::class);
    }

    public function gare(): BelongsTo
    {
        return $this->belongsTo(Gare::class, 'gare_id');
    }
}
