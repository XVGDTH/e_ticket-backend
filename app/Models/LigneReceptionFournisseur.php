<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LigneReceptionFournisseur extends Model
{
    protected $table = 'ci_lignes_reception_fournisseur';

    protected $fillable = [
        'reception_id',
        'article_id',
        'quantite',
    ];

    protected function casts(): array
    {
        return ['quantite' => 'decimal:3'];
    }

    public function reception(): BelongsTo
    {
        return $this->belongsTo(ReceptionFournisseur::class, 'reception_id');
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(ArticleStock::class, 'article_id');
    }
}
