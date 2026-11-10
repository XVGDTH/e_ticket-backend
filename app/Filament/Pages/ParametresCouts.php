<?php

namespace App\Filament\Pages;

use App\Models\CgParametre;
use App\Models\Compagnie;
use App\Services\ControleGestion\Acces;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

class ParametresCouts extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Paramètres des coûts';

    protected static string|UnitEnum|null $navigationGroup = 'Contrôle de gestion';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Paramètres des coûts';

    protected static ?string $slug = 'parametres-des-couts';

    protected string $view = 'filament.pages.parametres-couts';

    public string $compagnieId = '';

    public $norme_l100 = null;

    public $prix_litre = null;

    public $ecart_seuil_pct = null;

    public $commission_paiement_pct = null;

    public static function canAccess(): bool
    {
        return Acces::finance(auth()->user());
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Coûts standards et seuils utilisés par le contrôle de gestion';
    }

    public function mount(): void
    {
        $u = auth()->user();

        $this->compagnieId = $u->isSuperAdmin()
            ? (string) (Compagnie::orderBy('nom')->value('id') ?? '')
            : (string) ($u->compagnieId() ?? '');

        $this->charger();
    }

    public function updatedCompagnieId(): void
    {
        $this->charger();
    }

    protected function cid(): int
    {
        $u = auth()->user();

        return $u->isSuperAdmin() ? (int) $this->compagnieId : (int) ($u->compagnieId() ?? 0);
    }

    protected function charger(): void
    {
        if ($this->cid() <= 0) {
            return;
        }

        $p = CgParametre::pour($this->cid());

        $this->norme_l100 = $p['norme_l100'];
        $this->prix_litre = $p['prix_litre'];
        $this->ecart_seuil_pct = $p['ecart_seuil_pct'];
        $this->commission_paiement_pct = $p['commission_paiement_pct'];
    }

    protected function getViewData(): array
    {
        return [
            'compagnies' => auth()->user()->isSuperAdmin() ? Compagnie::orderBy('nom')->pluck('nom', 'id') : collect(),
        ];
    }

    public function enregistrer(): void
    {
        abort_unless(static::canAccess() && $this->cid() > 0, 403);

        $data = $this->validate([
            'norme_l100' => ['required', 'numeric', 'min:1', 'max:100'],
            'prix_litre' => ['required', 'integer', 'min:1'],
            'ecart_seuil_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'commission_paiement_pct' => ['required', 'numeric', 'min:0', 'max:20'],
        ]);

        CgParametre::updateOrCreate(['compagnie_id' => $this->cid()], [
            'norme_l100' => (float) $data['norme_l100'],
            'prix_litre' => (int) $data['prix_litre'],
            'ecart_seuil_pct' => (float) $data['ecart_seuil_pct'],
            'commission_paiement_pct' => (float) $data['commission_paiement_pct'],
        ]);

        Notification::make()->title('Paramètres enregistrés')->success()->send();
    }
}
