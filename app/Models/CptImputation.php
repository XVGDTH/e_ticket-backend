<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CptImputation extends Model
{
    protected $table = 'cpt_imputations';

    protected $guarded = [];

    protected $casts = ['montant' => 'integer', 'pourcentage' => 'float'];

    public function ligne(): BelongsTo
    {
        return $this->belongsTo(CptLigne::class, 'ligne_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(CptSection::class, 'section_id');
    }
}
