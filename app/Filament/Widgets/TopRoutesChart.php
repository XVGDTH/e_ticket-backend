<?php

namespace App\Filament\Widgets;

use App\Filament\Support\AnalyseChart;
use App\Services\Analytics\VentesAnalytics;

class TopRoutesChart extends AnalyseChart
{
    protected static ?int $sort = 8;

    public ?string $filter = '90j';

    protected static function roles(): array
    {
        return ['super_admin', 'admin_compagnie'];
    }

    protected function titre(): string
    {
        return 'Routes les plus rentables (top 10, CA en FCFA)';
    }

    protected function getFilters(): ?array
    {
        return VentesAnalytics::FENETRES;
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
        [$labels, $valeurs] = $this->analytics()->caParRoute($this->filter ?? '90j');

        // return $this->jeu($labels, $valeurs);
        return $this->jeu($labels, $valeurs, degrade: true);
    }
}