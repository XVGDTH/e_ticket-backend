<?php

namespace App\Filament\Widgets;

use App\Models\VerificationBillet;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AlertesControle extends BaseWidget
{
    protected static ?int $sort = 3;

    protected ?string $pollingInterval = '60s';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user && ($user->isSuperAdmin() || $user->isAdminCompagnie());
    }

    protected function getStats(): array
    {
        $base = fn () => VerificationBillet::query()->visiblePour(auth()->user());
        $semaine = now()->subDays(7);

        $aujourdhui = $base()->whereDate('created_at', today())->count();
        $faux = $base()->where('created_at', '>=', $semaine)->where('resultat', 'invalide')->count();
        $doubles = $base()->where('created_at', '>=', $semaine)->where('resultat', 'deja_utilise')->count();

        return [
            Stat::make('Billets contrôlés aujourd\'hui', $aujourdhui)->color('primary'),
            Stat::make('Faux billets détectés (7 j)', $faux)
                ->description('Codes non reconnus ou modifiés')
                ->color($faux > 0 ? 'danger' : 'gray'),
            Stat::make('Billets déjà utilisés présentés (7 j)', $doubles)
                ->description('Copies ou captures d\'écran possibles')
                ->color($doubles > 0 ? 'warning' : 'gray'),
        ];
    }
}
