<?php

namespace App\Filament\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait ScopedToCompagnie
{
    /**
     * Droits par rôle : voir, creer, modifier, supprimer.
     * À surcharger dans chaque ressource.
     */
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

    protected static function peut(string $action): bool
    {
        $role = static::roleStaff();

        return $role !== null && in_array($action, static::droits()[$role] ?? [], true);
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

        return match (static::roleStaff()) {
            'super_admin' => $query,
            'admin_compagnie' => static::scopeAdminCompagnie($query, $user),
            'agent_gare' => static::scopeToGare($query, $user->gareId()),
            default => $query->whereRaw('1 = 0'),
        };
    }

    /** Admin de compagnie : sa compagnie, et sa ville s'il en a une. */
    protected static function scopeAdminCompagnie(Builder $query, User $user): Builder
    {
        $query = static::scopeToCompagnie($query, $user->compagnieId());
        $villeId = $user->villeId();

        return $villeId ? static::scopeToVille($query, $villeId) : $query;
    }

    /** Par défaut : la table possède une colonne compagnie_id. */
    protected static function scopeToCompagnie(Builder $query, ?int $compagnieId): Builder
    {
        return $compagnieId
            ? $query->where($query->getModel()->getTable() . '.compagnie_id', $compagnieId)
            : $query->whereRaw('1 = 0');
    }

    /** Par défaut : l'agent ne voit rien. Chaque ressource dit ce qu'il peut voir. */
    protected static function scopeToGare(Builder $query, ?int $gareId): Builder
    {
        return $query->whereRaw('1 = 0');
    }

    /** Par défaut : fermé. Chaque ressource dit comment se restreindre à une ville. */
    protected static function scopeToVille(Builder $query, int $villeId): Builder
    {
        return $query->whereRaw('1 = 0');
    }
}