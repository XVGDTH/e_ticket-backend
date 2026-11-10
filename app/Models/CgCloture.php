<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CgCloture extends Model
{
    protected $table = 'cg_clotures';

    protected $guarded = [];

    protected $casts = ['snapshot' => 'array', 'cloture_le' => 'datetime'];

    public function clotureur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cloture_par');
    }
}
