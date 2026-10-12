<?php

namespace App\Filament\Widgets;

use App\Filament\Support\AnalyseChart;

class CroissanceChart extends AnalyseChart
{
    protected static ?int $sort = 3;

    protected function titre(): string
    {
        return 'Taux de croissance mensuel du CA (%), mois complets';
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        [$labels, $variations] = $this->analytics()->croissanceMensuelle();

        return [
            'datasets' => [[
                'label' => 'Variation vs mois précédent (%)',
                'data' => $variations,
                // 'backgroundColor' => array_map(fn ($v) => ($v ?? 0) >= 0 ? '#10b981' : '#ef4444', $variations),
                'backgroundColor' => array_map(
    fn ($v) => ($v ?? 0) >= 0 ? config('brand.primary') : config('brand.secondary'),
    $variations
),
            ]],
            'labels' => $labels,
        ];
    }
}