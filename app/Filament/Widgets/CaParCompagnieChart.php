<?php

namespace App\Filament\Widgets;

use App\Filament\Support\AnalyseChart;
use App\Services\Analytics\VentesAnalytics;

class CaParCompagnieChart extends AnalyseChart
{
    protected static ?int $sort = 9;

    public ?string $filter = '90j';

    protected static function roles(): array
    {
        return ['super_admin'];
    }

    protected function titre(): string
    {
        return 'CA par compagnie (FCFA)';
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
        return ['plugins' => ['legend' => ['display' => false]]];
    }

    protected function getData(): array
    {
        [$labels, $valeurs] = $this->analytics()->caParCompagnie($this->filter ?? '90j');

        return $this->jeu($labels, $valeurs);
    }
}