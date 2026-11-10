<?php

namespace App\Filament\Pages\Concerns;

use App\Models\Compagnie;
use Carbon\Carbon;
use Illuminate\Support\Collection;

trait ChoisitCompagnie
{
    public string $compagnieId = '';

    protected function initCompagnie(): void
    {
        $u = auth()->user();

        $this->compagnieId = $u->isSuperAdmin()
            ? ''
            : (string) ($u->compagnieId() ?? '');
    }

    protected function cid(): int
    {
        $u = auth()->user();

        if (! $u->isSuperAdmin()) {
            return (int) ($u->compagnieId() ?? 0);
        }

        if ($this->compagnieId === '') {
            return 0;
        }

        $compagnieId = (int) $this->compagnieId;

        abort_unless(Compagnie::query()->whereKey($compagnieId)->exists(), 404);

        return $compagnieId;
    }

    protected function compagniesChoix(): Collection
    {
        return auth()->user()->isSuperAdmin()
            ? collect(['' => 'Toutes les compagnies'])->union(Compagnie::query()->orderBy('nom')->pluck('nom', 'id'))
            : collect();
    }

    /**
     * @return array<int, int>
     */
    protected function cids(): array
    {
        $utilisateur = auth()->user();

        if (! $utilisateur->isSuperAdmin()) {
            return [(int) ($utilisateur->compagnieId() ?? 0)];
        }

        if ($this->compagnieId === '') {
            return Compagnie::query()->orderBy('id')->pluck('id')->map(fn ($id): int => (int) $id)->all();
        }

        $compagnieId = (int) $this->compagnieId;

        abort_unless(Compagnie::query()->whereKey($compagnieId)->exists(), 404);

        return [$compagnieId];
    }

    protected function toutesCompagnies(): bool
    {
        return auth()->user()->isSuperAdmin() && $this->compagnieId === '';
    }

    /** @return array<int,string> */
    protected function nomsMois(): array
    {
        return collect(range(1, 12))
            ->mapWithKeys(fn ($m) => [$m => Carbon::create(2000, $m, 1)->locale('fr')->translatedFormat('F')])
            ->all();
    }
}
