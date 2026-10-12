<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\Gare;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $lien = $this->record->compagnieRoles()->with('role')->first();

        $data['role'] = $lien?->role?->name;
        $data['compagnie_id'] = $lien?->compagnie_id;
        $data['gare_id'] = $lien?->gare_id;
        $data['ville_id'] = $lien?->ville_id;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $authUser = auth()->user();

        $role = $data['role'] ?? null;
        $gareId = $role === 'agent_gare' ? ($data['gare_id'] ?? null) : null;
        $villeId = $role === 'admin_compagnie' ? ($data['ville_id'] ?? null) : null;

        // La compagnie vient du serveur pour un admin de compagnie, jamais du formulaire.
        $compagnieId = $authUser->isSuperAdmin()
            ? ($data['compagnie_id'] ?? null)
            : $authUser->compagnieId();

        unset($data['role'], $data['compagnie_id'], $data['gare_id'], $data['ville_id']);

                // Mot de passe réinitialisé par un administrateur : l'utilisateur devra le changer.
        if (filled($data['password'] ?? null)) {
            $data['doit_changer_mot_de_passe'] = true;
        }

        // On ne peut pas désactiver son propre compte.
        if ($this->record->id === $authUser->id) {
            unset($data['actif']);
        }

        // Désactivation : coupe tout de suite les sessions du panel et les jetons de l'API.
        if (array_key_exists('actif', $data) && ! $data['actif']) {
            $this->record->tokens()->delete();
            DB::table('sessions')->where('user_id', $this->record->id)->delete();
        }

        if ($role) {
            // Seul le super admin attribue le rôle d'admin de compagnie.
            abort_if($role === 'admin_compagnie' && ! $authUser->isSuperAdmin(), 403);

            // La gare choisie doit appartenir à la compagnie.
            if ($role === 'agent_gare') {
                // abort_unless(
                //     $gareId && Gare::whereKey($gareId)->where('compagnie_id', $compagnieId)->exists(),
                //     403
                // );
                    abort_unless(
                    $gareId && $authUser->garesGerables($compagnieId)->whereKey($gareId)->exists(),
                    403
                );
            }

            $this->record->compagnieRoles()->delete();
            $this->record->assignerRolePourCompagnie($role, $compagnieId, $gareId, $villeId);
        }

        return $data;
    }
}