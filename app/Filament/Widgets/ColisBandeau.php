<?php

namespace App\Filament\Widgets;

use App\Models\Colis;
use Filament\Widgets\Widget;

class ColisBandeau extends Widget
{
    protected static bool $isDiscovered = false;

    protected string $view = 'filament.widgets.colis-bandeau';

    protected int|string|array $columnSpan = 'full';

    public function donnees(): array
    {
        $u = auth()->user();
        $base = fn () => Colis::query()->visiblePour($u);

        return [
            'enregistre' => $base()->where('statut', 'enregistre')->count(),
            'en_transit' => $base()->where('statut', 'en_transit')->count(),
            'arrive' => $base()->where('statut', 'arrive')->count(),
            'livre' => $base()->where('statut', 'livre')->where('remis_le', '>=', now()->subDays(30))->count(),
            'incident' => $base()->where('statut', 'incident')->count(),
            'aujourdhui' => $base()->whereDate('created_at', today())->count(),
            'encaisse' => (int) $base()->whereDate('paye_le', today())->sum('montant'),
        ];
    }
}
