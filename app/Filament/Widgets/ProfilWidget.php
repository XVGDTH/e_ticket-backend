<?php

namespace App\Filament\Widgets;

use App\Models\Compagnie;
use App\Models\Gare;
use App\Services\Analytics\VentesAnalytics;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ProfilWidget extends BaseWidget
{
    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';
    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return VentesAnalytics::role(auth()->user()) !== null;
    }

    protected function getStats(): array
    {
        $user = auth()->user();
        $role = VentesAnalytics::role($user);
        $profil = $user->profilStaff();

        $fonction = collect([$profil['role'], $profil['compagnie'], $profil['gare'], $profil['ville']])
            ->filter()
            ->implode(' · ');

        $identite = Stat::make('Connecté en tant que', trim("{$user->prenom} {$user->nom}"))
            ->description($fonction)
            ->descriptionIcon('heroicon-m-user-circle')
            ->color('primary');

        $perimetre = match ($role) {
            'super_admin' => Stat::make('Périmètre', 'Toutes les compagnies')
                ->description(Compagnie::count() . ' compagnies · ' . Gare::count() . ' gares'),
            'admin_compagnie' => Stat::make('Compagnie', $profil['compagnie'] ?? '—')
                ->description($profil['ville'] ? "Ville : {$profil['ville']}" : 'Toutes les villes du réseau'),
            default => Stat::make('Ma gare', $profil['gare'] ?? 'Aucune gare assignée')
                ->description(collect([$profil['compagnie'], $profil['ville']])->filter()->implode(' · '))
                ->color($profil['gare'] ? 'gray' : 'danger'),
        };

        return [$identite, $perimetre];
    }
}