<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CgBudget extends Model
{
    protected $table = 'cg_budgets';

    protected $guarded = [];

    protected $casts = [
        'valide_le' => 'datetime',
        'remplissage_cible_pct' => 'float',
        'norme_l100' => 'float',
        'commission_paiement_pct' => 'float',
        'fixes_car_mensuel' => 'integer',
    ];

    public function lignes(): HasMany
    {
        return $this->hasMany(CgBudgetLigne::class, 'budget_id');
    }

    public function estValide(): bool
    {
        return $this->statut === 'valide';
    }
}
