<?php

namespace App\Filament\Widgets;

use App\Filament\Support\AnalyseChart;
use App\Services\Analytics\VentesAnalytics;

class OperateursChart extends AnalyseChart
{
    protected static ?int $sort = 10;

    protected int|string|array $columnSpan = 1;

    public ?string $filter = '90j';

    protected function titre(): string
    {
        return 'Répartition par opérateur de paiement';
    }

    protected function getFilters(): ?array
    {
        return VentesAnalytics::FENETRES;
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        [$labels, $valeurs] = $this->analytics()->caParOperateur($this->filter ?? '90j');

        return $this->jeu($labels, $valeurs);
    }
}