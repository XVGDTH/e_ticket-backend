<?php

namespace App\Filament\Support;

use App\Services\Analytics\VentesAnalytics;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;

abstract class AnalyseChart extends ChartWidget
{
    protected int|string|array $columnSpan = 'full';
    protected ?string $pollingInterval = null;

    abstract protected function titre(): string;

    /** Rôles autorisés à voir ce graphique. */
    protected static function roles(): array
    {
        return ['super_admin', 'admin_compagnie', 'agent_gare'];
    }

    public function getHeading(): string|Htmlable|null
    {
        return $this->titre();
    }

    public static function canView(): bool
    {
        return in_array(VentesAnalytics::role(auth()->user()), static::roles(), true);
    }

    protected function analytics(): VentesAnalytics
    {
        return VentesAnalytics::pour(auth()->user());
    }

    // protected function palette(int $n): array
    // {
    //     $couleurs = ['#f59e0b', '#3b82f6', '#10b981', '#ef4444', '#8b5cf6', '#ec4899', '#14b8a6', '#f97316', '#6366f1', '#84cc16'];

    //     return array_map(fn ($i) => $couleurs[$i % count($couleurs)], range(0, max($n - 1, 0)));
    // }

    protected function palette(int $n): array
{
    $couleurs = config('brand.chart');

    return array_map(fn ($i) => $couleurs[$i % count($couleurs)], range(0, max($n - 1, 0)));
}

    /** Un jeu de données multicolore, utilisé par les barres et les anneaux. */
    // protected function jeu(array $labels, array $valeurs, string $nom = 'CA (FCFA)'): array
    // {
    //     return [
    //         'datasets' => [['label' => $nom, 'data' => $valeurs, 'backgroundColor' => $this->palette(count($valeurs))]],
    //         'labels' => $labels,
    //     ];
    // }

    /** Dégradé de n couleurs, de l'ambre au rouge de la charte. */
protected function degrade(int $n): array
{
    [$r1, $g1, $b1] = sscanf(config('brand.primary'), '#%02x%02x%02x');
    [$r2, $g2, $b2] = sscanf(config('brand.secondary'), '#%02x%02x%02x');

    return array_map(function ($i) use ($n, $r1, $g1, $b1, $r2, $g2, $b2) {
        $t = $n > 1 ? $i / ($n - 1) : 0;

        return sprintf(
            '#%02X%02X%02X',
            round($r1 + ($r2 - $r1) * $t),
            round($g1 + ($g2 - $g1) * $t),
            round($b1 + ($b2 - $b1) * $t),
        );
    }, range(0, max($n - 1, 0)));
}

/** Un jeu de données aux couleurs de la charte, utilisé par les barres et les anneaux. */
protected function jeu(array $labels, array $valeurs, string $nom = 'CA (FCFA)', bool $degrade = false): array
{
    $n = count($valeurs);

    return [
        'datasets' => [[
            'label' => $nom,
            'data' => $valeurs,
            'backgroundColor' => $degrade ? $this->degrade($n) : $this->palette($n),
        ]],
        'labels' => $labels,
    ];
}
}