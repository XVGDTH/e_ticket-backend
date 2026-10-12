<?php

namespace App\Filament\Widgets;

use App\Filament\Support\AnalyseChart;
use App\Services\Analytics\VentesAnalytics;

class CaParVilleChart extends AnalyseChart
{
    protected static ?int $sort = 7;

    protected int|string|array $columnSpan = 1;

    public ?string $filter = '90j';

    protected static function roles(): array
    {
        return ['super_admin', 'admin_compagnie'];
    }

    protected function titre(): string
    {
        return 'CA par ville de départ';
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
        [$labels, $valeurs] = $this->analytics()->caParVille($this->filter ?? '90j');

        return $this->jeu($labels, $valeurs);
    }
}