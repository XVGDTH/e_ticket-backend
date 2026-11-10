<?php

namespace App\Filament\Pages;

use App\Models\Compagnie;
use App\Services\Modules;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class ModulesDesGares extends Page
{
    protected string $view = 'filament.pages.modules-des-gares';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationLabel = 'Modules par gare';

    protected static string|UnitEnum|null $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 6;

    public string $compagnieId = '';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user && ($user->isSuperAdmin() || $user->isAdminCompagnie());
    }

    public function getTitle(): string
    {
        return 'Modules par gare';
    }

    public function mount(): void
    {
        $user = auth()->user();

        $this->compagnieId = $user->isSuperAdmin()
            ? (string) (Compagnie::orderBy('nom')->value('id') ?? '')
            : (string) $user->compagnieId();
    }

    protected function compagnieCourante(): int
    {
        $user = auth()->user();

        return $user->isSuperAdmin() ? (int) $this->compagnieId : (int) $user->compagnieId();
    }

    public function basculer(int $gareId, string $module): void
    {
        abort_unless(static::canAccess(), 403);

        $user = auth()->user();
        $compagnieId = $this->compagnieCourante();

        // Seuls les modules activés pour la compagnie, et seulement pour les gares de l'utilisateur.
        abort_unless($compagnieId && in_array($module, Modules::activesCompagnie($compagnieId), true), 403);
        abort_unless($user->garesGerables($compagnieId)->whereKey($gareId)->exists(), 403);

        Modules::definirGare($gareId, $module, ! in_array($module, Modules::activesGare($gareId), true));
    }

    public function toutPourModule(string $module, bool $actif): void
    {
        abort_unless(static::canAccess(), 403);

        $user = auth()->user();
        $compagnieId = $this->compagnieCourante();

        abort_unless($compagnieId && in_array($module, Modules::activesCompagnie($compagnieId), true), 403);

        foreach ($user->garesGerables($compagnieId)->pluck('gares.id') as $gareId) {
            Modules::definirGare((int) $gareId, $module, $actif);
        }

        Notification::make()
            ->title($actif ? 'Module attribué à toutes les gares' : 'Module retiré à toutes les gares')
            ->success()
            ->send();
    }

    public function donnees(): array
    {
        $user = auth()->user();
        $compagnieId = $this->compagnieCourante();

        $actifs = $compagnieId ? Modules::activesCompagnie($compagnieId) : [];
        $modules = collect(Modules::catalogue())->only($actifs)->all();

        $gares = $compagnieId
            ? $user->garesGerables($compagnieId)->with('ville')->orderBy('quartier')->get()
            : collect();

        $affectations = DB::table('modules_gares')
            ->whereIn('gare_id', $gares->pluck('id'))
            ->where('actif', true)
            ->get()
            ->groupBy('gare_id')
            ->map(fn ($lignes) => $lignes->pluck('module')->all())
            ->all();

        return [
            'compagnies' => $user->isSuperAdmin() ? Compagnie::orderBy('nom')->pluck('nom', 'id')->all() : [],
            'modules' => $modules,
            'gares' => $gares,
            'affectations' => $affectations,
        ];
    }
}
