<?php

namespace App\Models\Concerns;

use App\Models\Compagnie;
use App\Models\UserCompagnieRole;
use Illuminate\Support\Collection;

trait HasCompagnieRoles
{
    protected ?Collection $liensStaffCache = null;

    /**
     * Vérifie si l'utilisateur a le rôle donné, soit globalement
     * (compagnie_id null = super admin), soit pour la compagnie précise.
     */
    public function hasRoleForCompagnie(string $roleName, ?int $compagnieId = null): bool
    {
        return UserCompagnieRole::where('user_id', $this->id)
            ->whereHas('role', fn ($q) => $q->where('name', $roleName))
            ->where(function ($q) use ($compagnieId) {
                $q->whereNull('compagnie_id')
                    ->orWhere('compagnie_id', $compagnieId);
            })
            ->exists();
    }

    /**
     * Vérifie si l'utilisateur a ce rôle, quelle que soit la compagnie.
     */
    public function hasRoleInAnyCompagnie(string $roleName): bool
    {
        return UserCompagnieRole::where('user_id', $this->id)
            ->whereHas('role', fn ($q) => $q->where('name', $roleName))
            ->exists();
    }

    /**
     * La première compagnie à laquelle cet utilisateur est rattaché
     * (cas admin_compagnie / agent_gare, qui n'ont qu'une seule compagnie).
     */
    public function compagnieActuelle(): ?Compagnie
    {
        $lien = UserCompagnieRole::where('user_id', $this->id)
            ->whereNotNull('compagnie_id')
            ->first();

        return $lien?->compagnie;
    }

    public function compagnieRoles()
    {
        return $this->hasMany(UserCompagnieRole::class);
    }

    public function assignerRolePourCompagnie(string $roleName, ?int $compagnieId = null, ?int $gareId = null, ?int $villeId = null): void
    {
        $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => $roleName]);

        UserCompagnieRole::firstOrCreate([
            'user_id' => $this->id,
            'compagnie_id' => $compagnieId,
            'gare_id' => $gareId,
            'ville_id' => $villeId,
            'role_id' => $role->id,
        ]);

        $this->liensStaffCache = null;
    }

    /** Charge une seule fois les rôles de l'utilisateur pendant la requête. */
    protected function liensStaff(): Collection
    {
        return $this->liensStaffCache ??= $this->compagnieRoles()->with('role')->get();
    }

    public function isSuperAdmin(): bool
    {
        return $this->liensStaff()->contains(
            fn ($l) => $l->compagnie_id === null && $l->role?->name === 'super_admin'
        );
    }

    public function isAdminCompagnie(): bool
    {
        return $this->liensStaff()->contains(fn ($l) => $l->role?->name === 'admin_compagnie');
    }

    public function isAgentGare(): bool
    {
        return $this->liensStaff()->contains(fn ($l) => $l->role?->name === 'agent_gare');
    }

    public function compagnieId(): ?int
    {
        $id = $this->liensStaff()->whereNotNull('compagnie_id')->first()?->compagnie_id;

        return $id !== null ? (int) $id : null;
    }

    public function gareId(): ?int
    {
        $id = $this->liensStaff()->first(fn ($l) => $l->role?->name === 'agent_gare')?->gare_id;

        return $id !== null ? (int) $id : null;
    }

        /** Ville de l'administrateur de compagnie (null = toute la compagnie). */
    public function villeId(): ?int
    {
        $id = $this->liensStaff()->first(fn ($l) => $l->role?->name === 'admin_compagnie')?->ville_id;

        return $id !== null ? (int) $id : null;
    }

    /**
     * Gares que cet utilisateur a le droit d'affecter à un agent.
     * $compagnieId ne sert qu'au super admin, qui choisit la compagnie.
     */
    public function garesGerables(?int $compagnieId = null)
    {
        $gares = \App\Models\Gare::query();

        if ($this->isSuperAdmin()) {
            return $compagnieId ? $gares->where('compagnie_id', $compagnieId) : $gares->whereRaw('1 = 0');
        }

        if ($this->isAdminCompagnie()) {
            $gares->where('compagnie_id', $this->compagnieId());

            if ($villeId = $this->villeId()) {
                $gares->where('ville_id', $villeId);
            }

            return $gares;
        }

        return $gares->whereRaw('1 = 0');
    }

    /**
     * Libellés d'affichage du rôle, de la compagnie, de la ville et de la gare.
     *
     * @return array{role: string, compagnie: ?string, ville: ?string, gare: ?string}
     */
    public function profilStaff(): array
    {
        $lien = $this->compagnieRoles()->with(['role', 'compagnie', 'ville', 'gare.ville'])->first();

        return match ($lien?->role?->name) {
            'super_admin' => ['role' => 'Super administrateur', 'compagnie' => null, 'ville' => null, 'gare' => null],
            'admin_compagnie' => [
                'role' => 'Administrateur de compagnie',
                'compagnie' => $lien->compagnie?->nom,
                'ville' => $lien->ville?->nom,
                'gare' => null,
            ],
            'agent_gare' => [
                'role' => 'Agent de gare',
                'compagnie' => $lien->compagnie?->nom,
                'ville' => $lien->gare?->ville?->nom,
                'gare' => $lien->gare?->quartier,
            ],
            default => ['role' => 'Utilisateur', 'compagnie' => null, 'ville' => null, 'gare' => null],
        };
    }
}