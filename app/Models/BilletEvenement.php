<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BilletEvenement extends Model
{
    public $timestamps = false;

    protected $fillable = ['billet_id', 'statut_avant', 'statut_apres', 'user_id', 'motif', 'source', 'created_at'];

    protected $casts = ['created_at' => 'datetime'];

    public function billet(): BelongsTo
    {
        return $this->belongsTo(Billet::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}