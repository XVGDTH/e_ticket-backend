<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AvisGare extends Model
{
    use HasFactory;

    protected $fillable = ['billet_id', 'gare_id', 'scores', 'commentaire'];

    protected $casts = ['scores' => 'array'];

    protected static function booted(): void
    {
        // Un avis porte toujours sur la gare du billet concerné.
        static::creating(function (AvisGare $avis) {
            $gareId = Billet::whereKey($avis->billet_id)->value('gare_id');

            if ($gareId) {
                $avis->gare_id = $gareId;
            }
        });
    }

    public function billet(): BelongsTo
    {
        return $this->belongsTo(Billet::class);
    }

    public function gare(): BelongsTo
    {
        return $this->belongsTo(Gare::class);
    }
}