<?php

namespace App\Models;

use App\Models\Concerns\JournaliseParametres;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CgParametre extends Model
{
    use JournaliseParametres;

    protected $table = 'cg_parametres';

    protected $guarded = [];

    protected $casts = [
        'norme_l100' => 'float',
        'prix_litre' => 'integer',
        'ecart_seuil_pct' => 'float',
        'commission_paiement_pct' => 'float',
    ];

    protected function champsJournalises(): array
    {
        return [
            'norme_l100' => 'Norme de carburant (L/100 km)',
            'prix_litre' => 'Prix du litre',
            'ecart_seuil_pct' => 'Seuil d’écart (%)',
            'commission_paiement_pct' => 'Commission de paiement (%)',
        ];
    }

    public function compagnie(): BelongsTo
    {
        return $this->belongsTo(Compagnie::class);
    }

    /** Réglages effectifs d'une compagnie : les siens, sinon les valeurs de départ du fichier de config. */
    public static function pour(int $compagnieId): array
    {
        $p = static::query()->where('compagnie_id', $compagnieId)->first();

        return [
            'norme_l100' => (float) ($p?->norme_l100 ?? config('controle_gestion.carburant.norme_l100')),
            'prix_litre' => (int) ($p?->prix_litre ?? config('controle_gestion.carburant.prix_litre')),
            'ecart_seuil_pct' => (float) ($p?->ecart_seuil_pct ?? config('controle_gestion.ecart_seuil_pct')),
            'commission_paiement_pct' => (float) ($p?->commission_paiement_pct ?? 0),
        ];
    }
}
