<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MouvementStockFournisseur extends Model
{
    protected $table = 'ci_mouvements_stock';

    protected $fillable = [
        'compagnie_id',
        'article_id',
        'reception_id',
        'ligne_reception_id',
        'type',
        'quantite',
        'cree_par',
        'cree_par_nom',
        'cree_le',
    ];

    protected function casts(): array
    {
        return [
            'quantite' => 'decimal:3',
            'cree_le' => 'datetime',
        ];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(ArticleStock::class, 'article_id');
    }

    public function reception(): BelongsTo
    {
        return $this->belongsTo(ReceptionFournisseur::class, 'reception_id');
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par');
    }
}
