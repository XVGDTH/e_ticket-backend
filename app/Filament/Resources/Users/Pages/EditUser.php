<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $liens = $this->record->compagnieRoles()->with('role')->get();
        $compagnieId = $liens->whereNotNull('compagnie_id')->first()?->compagnie_id;

        // On ne propose que les rôles encore attribuables (module activé) ; les autres restent intacts.
        $data['roles'] = array_values(array_intersect(
            $liens->pluck('role.name')->unique()->all(),
            array_keys(UserResource::rolesAssignables($compagnieId ? (int) $compagnieId : null))
        ));
        $data['compagnie_id'] = $compagnieId;
        $data['gare_ids'] = $liens->pluck('gare_id')->filter()->unique()->values()->all();
        $data['ville_id'] = $liens->first(fn ($l) => $l->role?->name === 'admin_compagnie')?->ville_id;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $authUser = auth()->user();

        $roles = array_values(array_unique((array) ($data['roles'] ?? [])));
        $gareIds = array_values(array_unique(array_map('intval', (array) ($data['gare_ids'] ?? []))));
        $villeId = in_array('admin_compagnie', $roles, true) && filled($data['ville_id'] ?? null)
            ? (int) $data['ville_id'] : null;

        $compagnieId = $authUser->isSuperAdmin()
            ? (filled($data['compagnie_id'] ?? null) ? (int) $data['compagnie_id'] : null)
            : $authUser->compagnieId();

        unset($data['roles'], $data['compagnie_id'], $data['gare_ids'], $data['ville_id']);

        if (filled($data['password'] ?? null)) {
            $data['doit_changer_mot_de_passe'] = true;
        }

        if ($this->record->id === $authUser->id) {
            unset($data['actif']);
        }

        if (array_key_exists('actif', $data) && ! $data['actif']) {
            $this->record->tokens()->delete();
            DB::table('sessions')->where('user_id', $this->record->id)->delete();
        }

        UserResource::verifierAttribution($roles, $compagnieId, $gareIds);

        $this->record->definirRolesStaff(
            $roles, $compagnieId, $gareIds, $villeId,
            array_keys(UserResource::rolesAssignables($compagnieId))
        );

        return $data;
    }
}