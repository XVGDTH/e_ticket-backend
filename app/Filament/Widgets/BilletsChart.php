<?php

namespace App\Filament\Widgets;

use App\Filament\Support\AnalyseChart;
use App\Services\Analytics\VentesAnalytics;

class BilletsChart extends AnalyseChart
{
    protected static ?int $sort = 4;

    public ?string $filter = 'mois';

    protected function titre(): string
    {
        return 'Billets vendus et voyageurs';
    }

    protected function getFilters(): ?array
    {
        return VentesAnalytics::GRANULARITES;
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $g = $this->filter ?? 'mois';
        [$labels, $billets] = $this->analytics()->serie($g, 'COUNT(*)');
        [, $voyageurs] = $this->analytics()->serie($g, 'SUM(b.travelers_count)');

        return [
            'datasets' => [
                // ['label' => 'Billets', 'data' => $billets, 'backgroundColor' => '#3b82f6'],
                // ['label' => 'Voyageurs', 'data' => $voyageurs, 'backgroundColor' => '#f59e0b'],
                ['label' => 'Billets', 'data' => $billets, 'backgroundColor' => config('brand.secondary')],
                ['label' => 'Voyageurs', 'data' => $voyageurs, 'backgroundColor' => config('brand.primary')],
            ],
            'labels' => $labels,
        ];
    }
}