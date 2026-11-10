<?php

namespace App\Models\Concerns;

use App\Models\Compagnie;
use App\Models\Gare;
use App\Models\UserCompagnieRole;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

trait HasCompagnieRoles
{
    protected ?Collection $liensStaffCache = null;

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

    public function hasRoleInAnyCompagnie(string $roleName): bool
    {
        return UserCompagnieRole::where('user_id', $this->id)
            ->whereHas('role', fn ($q) => $q->where('name', $roleName))
            ->exists();
    }

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
        $role = Role::firstOrCreate(['name' => $roleName]);

        UserCompagnieRole::firstOrCreate([
            'user_id' => $this->id,
            'compagnie_id' => $compagnieId,
            'gare_id' => $gareId,
            'ville_id' => $villeId,
            'role_id' => $role->id,
        ]);

        $this->liensStaffCache = null;
    }

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

    public function isServiceClient(): bool
    {
        return $this->liensStaff()->contains(fn ($l) => $l->role?->name === 'service_client');
    }

    public function isServiceClientGlobal(): bool
    {
        return $this->liensStaff()->contains(
            fn ($l) => $l->compagnie_id === null && $l->role?->name === 'service_client'
        );
    }

    public function compagnieId(): ?int
    {
        $id = $this->liensStaff()->whereNotNull('compagnie_id')->first()?->compagnie_id;

        return $id !== null ? (int) $id : null;
    }

    public function gareId(): ?int
    {
        $id = $this->liensStaff()
            ->first(fn ($l) => in_array($l->role?->name, ['agent_gare', 'service_client'], true) && $l->gare_id !== null)
            ?->gare_id;

        return $id !== null ? (int) $id : null;
    }

    public function villeId(): ?int
    {
        $id = $this->liensStaff()->first(fn ($l) => $l->role?->name === 'admin_compagnie')?->ville_id;

        return $id !== null ? (int) $id : null;
    }

    public function garesGerables(?int $compagnieId = null)
    {
        $gares = Gare::query();

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

    // ───────────── Rôles métiers (config/modules.php → 'roles') ─────────────

    /** Tout compte du personnel (rôles de base + rôles métiers). Exclut le simple client. */
    public function aUnRoleStaff(): bool
    {
        $staff = array_merge(
            ['super_admin', 'admin_compagnie', 'agent_gare', 'service_client'],
            array_keys(config('modules.roles', []))
        );

        return $this->liensStaff()->contains(fn ($l) => in_array($l->role?->name, $staff, true));
    }

    protected function lienDonneAcces($lien, string $module): bool
    {
        foreach (config('modules.roles.'.$lien->role?->name.'.modules', []) as $m) {
            if ($module === $m || str_starts_with($module, $m.'.')) {
                return true;
            }
        }

        return false;
    }

    public function roleDonneAccesAuModule(string $module): bool
    {
        return $this->liensStaff()->contains(fn ($l) => $this->lienDonneAcces($l, $module));
    }

    /** Gares dont l'utilisateur voit les opérations pour ce module. */
    public function gareIdsPourModule(string $module): Collection
    {
        if ($this->isSuperAdmin()) {
            return Gare::pluck('id');
        }

        if ($this->isAdminCompagnie()) {
            return $this->garesGerables()->pluck('gares.id');
        }

        $ids = collect();

        foreach ($this->liensStaff()->filter(fn ($l) => $this->lienDonneAcces($l, $module)) as $l) {
            $ids = $l->gare_id
                ? $ids->push((int) $l->gare_id)
                : $ids->merge(Gare::where('compagnie_id', $l->compagnie_id)->pluck('id'));
        }

        return $ids->unique()->values();
    }

    /** Droits que les rôles métiers donnent sur ce module (lecture seule pour l'auditeur). */
    public function droitsMetierPourModule(string $module): array
    {
        $droits = [];

        foreach ($this->liensStaff() as $lien) {
            if (! $this->lienDonneAcces($lien, $module)) {
                continue;
            }

            $def = config('modules.roles.'.$lien->role?->name, []);
            $droits = array_merge(
                $droits,
                ($def['lecture_seule'] ?? false) ? ['voir'] : ($def['droits'] ?? ['voir', 'creer', 'modifier', 'supprimer'])
            );
        }

        return array_values(array_unique($droits));
    }

    public function libellesRoles(): array
    {
        $libelles = [
            'super_admin' => 'Super administrateur',
            'admin_compagnie' => 'Administrateur de compagnie',
            'agent_gare' => 'Agent de gare',
            'service_client' => 'Service client',
        ] + collect(config('modules.roles', []))->map(fn ($r) => $r['label'])->all();

        return $this->liensStaff()->map(fn ($l) => $libelles[$l->role?->name] ?? null)
            ->filter()->unique()->values()->all();
    }

    public function libellesGares(): array
    {
        return $this->compagnieRoles()->with('gare')->get()
            ->map(fn ($l) => $l->gare?->quartier)->filter()->unique()->values()->all();
    }

    /** Remplace les rôles gérés d'un utilisateur par la nouvelle sélection (sans tout recréer). */
    public function definirRolesStaff(array $roles, ?int $compagnieId, array $gareIds, ?int $villeId, array $rolesGeres): void
    {
        DB::transaction(function () use ($roles, $compagnieId, $gareIds, $villeId, $rolesGeres) {
            $voulus = collect();

            foreach ($roles as $nom) {
                $roleId = Role::firstOrCreate(['name' => $nom])->id;
                $gares = ($nom === 'admin_compagnie' || ! $gareIds) ? [null] : $gareIds;

                foreach ($gares as $gareId) {
                    $voulus->push(implode('|', [
                        $roleId, $compagnieId ?? 0, $gareId ?? 0,
                        $nom === 'admin_compagnie' ? ($villeId ?? 0) : 0,
                    ]));
                }
            }
            $voulus = $voulus->unique()->values();

            $idsGeres = Role::whereIn('name', $rolesGeres)->pluck('id');
            $gardes = [];

            foreach (UserCompagnieRole::where('user_id', $this->id)->whereIn('role_id', $idsGeres)->get() as $lien) {
                $cle = implode('|', [$lien->role_id, $lien->compagnie_id ?? 0, $lien->gare_id ?? 0, $lien->ville_id ?? 0]);

                if ($voulus->contains($cle) && ! isset($gardes[$cle])) {
                    $gardes[$cle] = true;
                } else {
                    $lien->delete();
                }
            }

            foreach ($voulus->diff(array_keys($gardes)) as $cle) {
                [$r, $c, $g, $v] = array_map('intval', explode('|', $cle));

                UserCompagnieRole::create([
                    'user_id' => $this->id,
                    'compagnie_id' => $c ?: null,
                    'gare_id' => $g ?: null,
                    'ville_id' => $v ?: null,
                    'role_id' => $r,
                ]);
            }
        });

        $this->liensStaffCache = null;
    }

    // public function profilStaff(): array
    // {
    //     $lien = $this->compagnieRoles()->with(['role', 'compagnie', 'ville', 'gare.ville'])->first();

    //     return match ($lien?->role?->name) {
    //         'super_admin' => ['role' => 'Super administrateur', 'compagnie' => null, 'ville' => null, 'gare' => null],
    //         'admin_compagnie' => [
    //             'role' => 'Administrateur de compagnie',
    //             'compagnie' => $lien->compagnie?->nom,
    //             'ville' => $lien->ville?->nom,
    //             'gare' => null,
    //         ],
    //         'agent_gare' => [
    //             'role' => 'Agent de gare',
    //             'compagnie' => $lien->compagnie?->nom,
    //             'ville' => $lien->gare?->ville?->nom,
    //             'gare' => $lien->gare?->quartier,
    //         ],
    //         'service_client' => [
    //             'role' => 'Service client',
    //             'compagnie' => $lien->compagnie?->nom,
    //             'ville' => $lien->gare?->ville?->nom,
    //             'gare' => $lien->gare?->quartier,
    //         ],
    //         default => ['role' => 'Utilisateur', 'compagnie' => null, 'ville' => null, 'gare' => null],
    //     };
    // }
    public function profilStaff(): array
{
    $liens = $this->compagnieRoles()->with(['role', 'compagnie', 'ville', 'gare.ville'])->get();

    $lien = $liens->first(fn ($l) => $l->role?->name === 'super_admin')
        ?? $liens->first(fn ($l) => $l->role?->name === 'admin_compagnie')
        ?? $liens->first(fn ($l) => $l->compagnie_id !== null)
        ?? $liens->first();

    $roles = $this->libellesRoles();
    $gares = $liens->map(fn ($l) => $l->gare?->quartier)->filter()->unique()->values();

    return [
        'role' => $roles ? implode(' · ', $roles) : 'Utilisateur',
        'roles' => $roles,
        'compagnie' => $lien?->compagnie?->nom,
        'ville' => $lien?->ville?->nom ?? $lien?->gare?->ville?->nom,
        'gare' => match (true) {
            $gares->isEmpty() => null,
            $gares->count() === 1 => $gares->first(),
            default => $gares->count().' gares',
        },
    ];
}
}