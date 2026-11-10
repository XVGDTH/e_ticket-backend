<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ArticleStock extends Model
{
    protected $table = 'ci_articles_stock';

    protected $fillable = [
        'compagnie_id',
        'reference',
        'nom',
        'unite',
        'quantite_stock',
        'cree_par',
    ];

    protected function casts(): array
    {
        return ['quantite_stock' => 'decimal:3'];
    }

    public function compagnie(): BelongsTo
    {
        return $this->belongsTo(Compagnie::class);
    }

    public function lignesReception(): HasMany
    {
        return $this->hasMany(LigneReceptionFournisseur::class, 'article_id');
    }

    public function mouvements(): HasMany
    {
        return $this->hasMany(MouvementStockFournisseur::class, 'article_id');
    }
}
