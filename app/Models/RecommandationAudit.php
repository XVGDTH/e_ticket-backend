<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecommandationAudit extends Model
{
    use HasFactory;

    protected $table = 'ci_recommandations';

    protected $fillable = [
        'compagnie_id',
        'controle_id',
        'titre',
        'constat',
        'action_requise',
        'priorite',
        'statut',
        'responsable_id',
        'echeance',
        'resolution',
        'terminee_le',
        'cree_par',
    ];

    protected function casts(): array
    {
        return [
            'echeance' => 'date',
            'terminee_le' => 'datetime',
        ];
    }

    public function compagnie(): BelongsTo
    {
        return $this->belongsTo(Compagnie::class);
    }

    public function controle(): BelongsTo
    {
        return $this->belongsTo(ControleAudit::class, 'controle_id');
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par');
    }
}
