<?php

namespace App\Filament\Widgets;

use App\Filament\Support\AnalyseChart;

class RemplissageParRouteChart extends AnalyseChart
{
    protected static ?int $sort = 13;

    public ?string $filter = 'top';

    protected function titre(): string
    {
        return 'Taux de remplissage par route (30 derniers jours terminés, %)';
    }

    protected function getFilters(): ?array
    {
        return ['top' => 'Routes les mieux remplies', 'bas' => 'Routes les moins remplies'];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return ['indexAxis' => 'y', 'plugins' => ['legend' => ['display' => false]]];
    }

    protected function getData(): array
    {
        [$labels, $taux] = $this->analytics()->remplissageParRoute($this->filter ?? 'top');

        return [
            'datasets' => [[
                'label' => 'Remplissage (%)',
                'data' => $taux,
                'backgroundColor' => array_map(
                    fn ($t) => $t >= 70 ? '#10b981' : ($t >= 40 ? '#f59e0b' : '#ef4444'),
                    $taux
                ),
            ]],
            'labels' => $labels,
        ];
    }
}