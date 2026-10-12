<?php

namespace App\Filament\Widgets;

use App\Filament\Support\AnalyseChart;
use App\Services\Analytics\VentesAnalytics;

class ClientsChart extends AnalyseChart
{
    protected static ?int $sort = 5;

    public ?string $filter = 'mois';

    protected function titre(): string
    {
        return 'Clients : nouveaux et actifs';
    }

    protected function getFilters(): ?array
    {
        return VentesAnalytics::GRANULARITES;
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        [$labels, $nouveaux, $actifs] = $this->analytics()->clients($this->filter ?? 'mois');

        return [
            'datasets' => [
                ['label' => 'Nouveaux clients', 'data' => $nouveaux, 'borderColor' => config('brand.primary'), 'tension' => 0.3],
                ['label' => 'Clients actifs', 'data' => $actifs,  'borderColor' => config('brand.secondary'), 'tension' => 0.3],

                   // Nouveaux clients
                 
            ],
            'labels' => $labels,
        ];
    }
}