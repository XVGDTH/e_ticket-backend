<?php

namespace App\Filament\Pages;

use App\Models\Compagnie;
use App\Services\Modules;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use UnitEnum;

class GestionModules extends Page
{
    protected string $view = 'filament.pages.gestion-modules';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-squares-plus';

    protected static ?string $navigationLabel = 'Modules des compagnies';

    protected static string|UnitEnum|null $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 5;

    public string $compagnieId = '';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isSuperAdmin();
    }

    public function getTitle(): string
    {
        return 'Modules des compagnies';
    }

    public function mount(): void
    {
        $this->compagnieId = (string) (Compagnie::orderBy('nom')->value('id') ?? '');
    }

    public function basculer(string $module): void
    {
        abort_unless(static::canAccess(), 403);
        abort_unless(isset(Modules::catalogue()[$module]), 404);

        $compagnie = Compagnie::findOrFail((int) $this->compagnieId);
        $actif = ! in_array($module, Modules::activesCompagnie($compagnie->id), true);

        Modules::definirCompagnie($compagnie->id, $module, $actif);

        Notification::make()
            ->title(Modules::catalogue()[$module]['nom'].($actif ? ' activé' : ' désactivé'))
            ->body($compagnie->nom)
            ->success()
            ->send();
    }

    public function toutChanger(bool $actif): void
    {
        abort_unless(static::canAccess(), 403);

        $compagnie = Compagnie::findOrFail((int) $this->compagnieId);

        foreach (array_keys(Modules::catalogue()) as $module) {
            Modules::definirCompagnie($compagnie->id, $module, $actif);
        }

        Notification::make()
            ->title($actif ? 'Tous les modules sont activés' : 'Tous les modules sont désactivés')
            ->body($compagnie->nom)
            ->success()
            ->send();
    }

    public function donnees(): array
    {
        $compagnies = Compagnie::orderBy('nom')->pluck('nom', 'id')->all();
        $id = (int) $this->compagnieId;

        return [
            'compagnies' => $compagnies,
            'nom' => $compagnies[$id] ?? '—',
            'actives' => $id ? Modules::activesCompagnie($id) : [],
            'catalogue' => Modules::catalogue(),
        ];
    }
}
