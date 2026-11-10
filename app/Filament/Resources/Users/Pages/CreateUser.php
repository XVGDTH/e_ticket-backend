<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected array $roles = [];
    protected ?int $compagnieId = null;
    protected array $gareIds = [];
    protected ?int $villeId = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $authUser = auth()->user();

        $this->roles = array_values(array_unique((array) ($data['roles'] ?? [])));

        // La compagnie vient du serveur pour un admin de compagnie, jamais du formulaire.
        // Pour le super admin, une compagnie vide veut dire « service client national ».
        $this->compagnieId = $authUser->isSuperAdmin()
            ? (filled($data['compagnie_id'] ?? null) ? (int) $data['compagnie_id'] : null)
            : $authUser->compagnieId();

        $this->gareIds = array_values(array_unique(array_map('intval', (array) ($data['gare_ids'] ?? []))));
        $this->villeId = in_array('admin_compagnie', $this->roles, true) && filled($data['ville_id'] ?? null)
            ? (int) $data['ville_id'] : null;

        UserResource::verifierAttribution($this->roles, $this->compagnieId, $this->gareIds);

        unset($data['roles'], $data['compagnie_id'], $data['gare_ids'], $data['ville_id']);

        $data['telephone_verifie'] = true;
        // Mot de passe défini par l'administrateur : l'utilisateur devra le changer à sa première connexion.
        $data['doit_changer_mot_de_passe'] = true;

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        $record = parent::handleRecordCreation($data);

        $record->definirRolesStaff(
            $this->roles, $this->compagnieId, $this->gareIds, $this->villeId,
            array_keys(UserResource::rolesAssignables($this->compagnieId))
        );

        return $record;
    }
}