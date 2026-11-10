<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ControleAudit extends Model
{
    use HasFactory;

    protected $table = 'ci_controles';

    protected $fillable = [
        'compagnie_id',
        'titre',
        'objectif',
        'procedure',
        'frequence',
        'niveau_risque',
        'responsable_id',
        'prochaine_echeance',
        'actif',
        'cree_par',
    ];

    protected function casts(): array
    {
        return [
            'prochaine_echeance' => 'date',
            'actif' => 'boolean',
        ];
    }

    public function compagnie(): BelongsTo
    {
        return $this->belongsTo(Compagnie::class);
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par');
    }

    public function recommandations(): HasMany
    {
        return $this->hasMany(RecommandationAudit::class, 'controle_id');
    }
}
