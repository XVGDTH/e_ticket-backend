<?php

namespace App\Filament\Widgets;

use App\Filament\Support\AnalyseChart;
use App\Services\Analytics\VentesAnalytics;
use Filament\Support\RawJs;

class ChiffreAffairesChart extends AnalyseChart
{
    protected static ?int $sort = 2;

    public ?string $filter = 'mois';

    protected function titre(): string
    {
        return "Évolution du chiffre d'affaires (FCFA)";
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
        [$labels, $valeurs] = $this->analytics()->serie($this->filter ?? 'mois', 'SUM(b.montant_transport)');

        return [
            'datasets' => [[
                'label' => 'CA (FCFA)',
                'data' => $valeurs,
                // 'borderColor' => '#f59e0b',
                // 'backgroundColor' => 'rgba(245, 158, 11, 0.15)',
               'pointBackgroundColor' => config('brand.secondary'),
               'pointBorderColor' => config('brand.primary'),
                'fill' => true,
                'tension' => 0.3,
            ]],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array|RawJs|null
{
    $ambre = config('brand.primary');
    $rouge = config('brand.secondary');

    return RawJs::make(<<<JS
    {
        elements: {
            line: {
                borderColor: (ctx) => {
                    const a = ctx.chart.chartArea;
                    if (!a) return '{$ambre}';
                    const g = ctx.chart.ctx.createLinearGradient(a.left, 0, a.right, 0);
                    g.addColorStop(0, '{$ambre}');
                    g.addColorStop(0.75, '{$rouge}');
                    return g;
                },
                backgroundColor: (ctx) => {
                    const a = ctx.chart.chartArea;
                    if (!a) return '{$ambre}26';
                    const g = ctx.chart.ctx.createLinearGradient(0, a.top, 0, a.bottom);
                    g.addColorStop(0, '{$rouge}40');
                    g.addColorStop(1, '{$ambre}05');
                    return g;
                },
            },
        },
    }
    JS);
}
}