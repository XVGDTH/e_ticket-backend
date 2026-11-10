<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SequenceDocument extends Model
{
    protected $table = 'ci_sequences_documents';

    protected $fillable = [
        'compagnie_id',
        'serie',
        'annee',
        'dernier_numero',
    ];

    public function compagnie(): BelongsTo
    {
        return $this->belongsTo(Compagnie::class);
    }
}
