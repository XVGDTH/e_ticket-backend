<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\DemandesAssistance\DemandeAssistanceResource;
use App\Models\DemandeAssistance;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AssistanceOverview extends BaseWidget
{
    protected static ?int $sort = 2;

    protected ?string $pollingInterval = '30s';

    public static function canView(): bool
    {
        return DemandeAssistanceResource::canViewAny();
    }

    protected function getStats(): array
    {
        $base = fn () => DemandeAssistance::query()->visiblePour(auth()->user());

        $nouvelles = $base()->where('statut', 'nouvelle')->count();
        $enCours = $base()->where('statut', 'en_cours')->count();
        $rappels = $base()->where('rappel_statut', 'demande')->count();
        $note = $base()->whereNotNull('note')->where('resolue_le', '>=', now()->subDays(30))->avg('note');

        return [
            Stat::make('Nouvelles demandes', $nouvelles)
                ->description('En attente d\'une première réponse')
                ->color($nouvelles > 0 ? 'danger' : 'gray'),
            Stat::make('En cours', $enCours)->color($enCours > 0 ? 'warning' : 'gray'),
            Stat::make('Rappels à faire', $rappels)->color($rappels > 0 ? 'danger' : 'gray'),
            Stat::make('Satisfaction (30 j)', $note ? number_format($note, 1, ',', ' ').' / 5' : '—')
                ->color($note === null ? 'gray' : ($note >= 4 ? 'success' : 'warning')),
        ];
    }
}
