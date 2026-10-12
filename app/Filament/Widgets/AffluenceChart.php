<?php

namespace App\Filament\Widgets;

use App\Filament\Support\AnalyseChart;

class AffluenceChart extends AnalyseChart
{
    protected static ?int $sort = 11;

    protected int|string|array $columnSpan = 1;

    public ?string $filter = 'jour';

    protected function titre(): string
    {
        return 'Affluence des voyageurs (90 derniers jours)';
    }

    protected function getFilters(): ?array
    {
        return ['jour' => 'Par jour de la semaine', 'heure' => 'Par heure de départ'];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return ['plugins' => ['legend' => ['display' => false]]];
    }

    protected function getData(): array
    {
        [$labels, $valeurs] = $this->analytics()->affluence($this->filter ?? 'jour');

        return [
            'datasets' => [['label' => 'Voyageurs', 'data' => $valeurs, 'backgroundColor' => config('brand.primary')
            ]],
            'labels' => $labels,
        ];
    }
}