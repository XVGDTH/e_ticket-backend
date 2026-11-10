<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageAssistance extends Model
{
    protected $table = 'messages_assistance';

    protected $fillable = ['demande_id', 'auteur_id', 'auteur_type', 'corps', 'lu_le'];

    protected $casts = ['lu_le' => 'datetime'];

    public function demande(): BelongsTo
    {
        return $this->belongsTo(DemandeAssistance::class, 'demande_id');
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auteur_id');
    }
}
