<?php

namespace App\Filament\Pages;

use App\Filament\Resources\DepensesVehicules\DepenseVehiculeResource;
use App\Filament\Resources\Vehicules\VehiculeResource;
use App\Models\Compagnie;
use App\Models\DepenseVehicule;
use App\Models\User;
use App\Models\Vehicule;
use App\Services\Flotte\Acces;
use App\Services\Modules;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use UnitEnum;

class MaintenanceFlotte extends Page
{
    protected string $view = 'filament.pages.maintenance-flotte';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $navigationLabel = 'Maintenance et carburant';

    protected static string|UnitEnum|null $navigationGroup = 'Flotte';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'maintenance-et-carburant';

    public string $compagnieId = '';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && Modules::visiblePour($user, 'maintenance')
            && ($user->isSuperAdmin() || $user->isAdminCompagnie() || $user->isAgentGare() || $user->roleDonneAccesAuModule('maintenance'));
    }

    public function getTitle(): string
    {
        return 'Maintenance et carburant';
    }

    public function mount(): void
    {
        $user = auth()->user();

        $this->compagnieId = $user?->isSuperAdmin()
            ? ''
            : (string) ($user?->compagnieId() ?? '');
    }

    /**
     * @return array{
     *     compagnies: array<int, string>,
     *     compagnieId: int,
     *     nomCompagnie: string,
     *     periode: string,
     *     statistiques: array<string, int>,
     *     vehiculesAlerte: Collection<int, Vehicule>,
     *     depensesRecentes: \Illuminate\Database\Eloquent\Collection<int, DepenseVehicule>,
     *     afficherActionsFlotte: bool,
     *     urlVehicules: string,
     *     urlDepenses: string
     * }
     */
    protected function getViewData(): array
    {
        $user = auth()->user();
        abort_unless($user instanceof User && static::canAccess(), 403);

        $compagnies = $user->isSuperAdmin()
            ? ['' => 'Toutes les compagnies'] + Compagnie::query()->where('actif', true)->orderBy('nom')->pluck('nom', 'id')->all()
            : [];
        $compagnieId = $user->isSuperAdmin()
            ? (int) (array_key_exists($this->compagnieId, $compagnies) ? $this->compagnieId : '')
            : (int) ($user->compagnieId() ?? 0);
        $nomCompagnie = $compagnieId > 0
            ? ($compagnies[$compagnieId] ?? Compagnie::query()->whereKey($compagnieId)->value('nom') ?? '—')
            : 'Toutes les compagnies';

        $vehicules = Vehicule::query()
            ->visiblePour($user)
            ->when($compagnieId > 0, fn (Builder $query) => $query->where('compagnie_id', $compagnieId))
            ->orderBy('immatriculation')
            ->get();

        $moisDebut = today()->startOfMonth();
        $depensesDuMois = $this->depensesPour($user, $compagnieId)
            ->whereBetween('date_depense', [$moisDebut->toDateString(), today()->toDateString()])
            ->get(['categorie', 'montant']);
        $depensesRecentes = $this->depensesPour($user, $compagnieId)
            ->with('vehicule:id,immatriculation,marque,modele')
            ->orderByDesc('date_depense')
            ->orderByDesc('id')
            ->limit(6)
            ->get();
        $margeVidange = (int) config('flotte.vidange_marge_km', 500);
        $vehiculesAlerte = $vehicules
            ->filter(fn (Vehicule $vehicule): bool => in_array($vehicule->statut, ['maintenance', 'panne'], true)
                || ($vehicule->km_prochaine_vidange !== null
                    && (int) $vehicule->km_prochaine_vidange <= (int) $vehicule->km_actuel + $margeVidange)
                || collect($vehicule->documents())->contains(
                    fn (array $document): bool => $document['jours'] <= (int) config('flotte.alerte_jours', 30)
                ))
            ->take(6)
            ->values();

        return [
            'compagnies' => $compagnies,
            'compagnieId' => $compagnieId,
            'nomCompagnie' => $nomCompagnie,
            'periode' => today()->format('m/Y'),
            'statistiques' => [
                'vehicules' => $vehicules->count(),
                'immobilises' => $vehicules->whereIn('statut', ['maintenance', 'panne'])->count(),
                'depenses_maintenance' => (int) $depensesDuMois
                    ->whereIn('categorie', ['entretien', 'reparation', 'pneus', 'lavage'])
                    ->sum('montant'),
                'depenses_carburant' => (int) $depensesDuMois
                    ->where('categorie', 'carburant')
                    ->sum('montant'),
            ],
            'vehiculesAlerte' => $vehiculesAlerte,
            'depensesRecentes' => $depensesRecentes,
            'afficherActionsFlotte' => Acces::visible($user),
            'urlVehicules' => VehiculeResource::getUrl('index'),
            'urlDepenses' => DepenseVehiculeResource::getUrl('index'),
        ];
    }

    /**
     * @return Builder<DepenseVehicule>
     */
    private function depensesPour(User $user, int $compagnieId): Builder
    {
        return DepenseVehicule::query()
            ->visiblePour($user)
            ->when($compagnieId > 0, fn (Builder $query) => $query->where('compagnie_id', $compagnieId));
    }
}
