<?php

namespace App\Filament\Concerns;

use App\Models\User;
use App\Services\Modules;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

trait ScopedToCompagnie
{
    protected static function droits(): array
    {
        return [];
    }

    protected static function roleStaff(): ?string
    {
        $user = auth()->user();

        return match (true) {
            $user === null => null,
            $user->isSuperAdmin() => 'super_admin',
            $user->isAdminCompagnie() => 'admin_compagnie',
            $user->isAgentGare() => 'agent_gare',
            default => null,
        };
    }

    /** Cette ressource appartient-elle à un module (trait ConditionneParModule) ? */
    protected static function estUnModule(): bool
    {
        return method_exists(static::class, 'moduleCle');
    }

    /** L'utilisateur a-t-il un rôle métier qui donne accès au module de cette ressource ? */
    protected static function aRoleMetier(?User $user): bool
    {
        return $user !== null
            && static::estUnModule()
            && $user->roleDonneAccesAuModule(static::moduleCle());
    }

    protected static function peut(string $action): bool
    {
        $user = auth()->user();
        $role = static::roleStaff();

        if ($role !== null && in_array($action, static::droits()[$role] ?? [], true)) {
            return true;
        }

        // Rôles métiers : droits définis dans config/modules.php (lecture seule pour l'auditeur).
        return static::aRoleMetier($user)
            && Modules::visiblePour($user, static::moduleCle())
            && in_array($action, $user->droitsMetierPourModule(static::moduleCle()), true);
    }

    public static function canViewAny(): bool { return static::peut('voir'); }
    public static function canCreate(): bool { return static::peut('creer'); }
    public static function canEdit(Model $record): bool { return static::peut('modifier'); }
    public static function canDelete(Model $record): bool { return static::peut('supprimer'); }
    public static function canDeleteAny(): bool { return static::peut('supprimer'); }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();
        $role = static::roleStaff();

        if ($role === 'super_admin') {
            return $query;
        }

        if ($role === 'admin_compagnie') {
            return static::scopeAdminCompagnie($query, $user);
        }

        if (static::aRoleMetier($user)) {
            return static::scopeMetier($query, $user);
        }

        return $role === 'agent_gare'
            ? static::scopeToGare($query, $user->gareId())
            : $query->whereRaw('1 = 0');
    }

    protected static function scopeAdminCompagnie(Builder $query, User $user): Builder
    {
        $query = static::scopeToCompagnie($query, $user->compagnieId());
        $villeId = $user->villeId();

        return $villeId ? static::scopeToVille($query, $villeId) : $query;
    }

    /** Rôle métier : sa compagnie, puis les gares où il travaille et où le module est attribué. */
    /** Rôle métier : toute sa compagnie (toutes les gares). */
    protected static function scopeMetier(Builder $query, User $user): Builder
    {
        return static::scopeToCompagnie($query, $user->compagnieId());
    }

    protected static function scopeToCompagnie(Builder $query, ?int $compagnieId): Builder
    {
        return $compagnieId
            ? $query->where($query->getModel()->getTable().'.compagnie_id', $compagnieId)
            : $query->whereRaw('1 = 0');
    }

    protected static function scopeToGare(Builder $query, ?int $gareId): Builder
    {
        return $query->whereRaw('1 = 0');
    }

    /**
     * Par défaut : filtre sur gare_id si la table l'a ; sinon (données de compagnie,
     * comme le personnel) la restriction à la compagnie suffit. À surcharger si besoin.
     */
    protected static function scopeToGares(Builder $query, array $gareIds): Builder
    {
        if ($gareIds === []) {
            return $query->whereRaw('1 = 0');
        }

        $table = $query->getModel()->getTable();

        return Schema::hasColumn($table, 'gare_id')
            ? $query->whereIn($table.'.gare_id', $gareIds)
            : $query;
    }

    protected static function scopeToVille(Builder $query, int $villeId): Builder
    {
        return $query->whereRaw('1 = 0');
    }
}