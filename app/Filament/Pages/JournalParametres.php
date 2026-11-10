<?php

namespace App\Filament\Pages;

use App\Models\Compagnie;
use App\Models\JournalParametre;
use App\Services\AuditInterne\Acces;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class JournalParametres extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Journal des paramètres';

    protected static string|UnitEnum|null $navigationGroup = 'Contrôle interne et audit';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'journal-parametres';

    protected string $view = 'filament.pages.journal-parametres';

    public string $compagnieId = '';

    public static function canAccess(): bool
    {
        return Acces::visible(auth()->user());
    }

    public function getTitle(): string
    {
        return 'Journal des paramètres';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Historique en lecture seule des changements de tarifs, quotas, seuils, répartitions et droits.';
    }

    public function updatedCompagnieId(): void
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        if ($this->compagnieId !== '') {
            abort_unless(Compagnie::query()->whereKey((int) $this->compagnieId)->exists(), 404);
        }
    }

    protected function getViewData(): array
    {
        $utilisateur = auth()->user();

        abort_unless(Acces::visible($utilisateur), 403);

        $requete = JournalParametre::query();

        if ($utilisateur->isSuperAdmin()) {
            if ($this->compagnieId !== '') {
                abort_unless(Compagnie::query()->whereKey((int) $this->compagnieId)->exists(), 404);
                $this->limiterALaCompagnieOuAuxTarifsGeneraux($requete, (int) $this->compagnieId);
            }
        } else {
            $this->limiterALaCompagnieOuAuxTarifsGeneraux($requete, $utilisateur->compagnieId() ?? 0);
        }

        return [
            'compagnies' => $utilisateur->isSuperAdmin()
                ? Compagnie::query()->orderBy('nom')->pluck('nom', 'id')
                : collect(),
            'journal' => (clone $requete)
                ->orderByDesc('enregistre_le')
                ->orderByDesc('id')
                ->limit(60)
                ->get(),
            'total' => (clone $requete)->count(),
            'aujourdHui' => (clone $requete)->whereDate('enregistre_le', today())->count(),
        ];
    }

    private function limiterALaCompagnieOuAuxTarifsGeneraux(Builder $requete, int $compagnieId): void
    {
        $requete->where(function (Builder $portee) use ($compagnieId): void {
            $portee->where('compagnie_id', $compagnieId)
                ->orWhere(function (Builder $generale): void {
                    $generale->whereNull('compagnie_id')
                        ->where('entite', 'frais_regles');
                });
        });
    }
}
