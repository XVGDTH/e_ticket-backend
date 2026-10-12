<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\Gare;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected ?string $role = null;
    protected ?int $compagnieId = null;
    protected ?int $gareId = null;
    protected ?int $villeId = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $authUser = auth()->user();

        $this->role = $data['role'];

        // Seul le super admin crée des admins de compagnie.
        abort_if($this->role === 'admin_compagnie' && ! $authUser->isSuperAdmin(), 403);

        // La compagnie vient du serveur pour un admin de compagnie, jamais du formulaire.
        $this->compagnieId = $authUser->isSuperAdmin()
            ? ($data['compagnie_id'] ?? null)
            : $authUser->compagnieId();

        $this->gareId = $this->role === 'agent_gare' ? ($data['gare_id'] ?? null) : null;
        $this->villeId = $this->role === 'admin_compagnie' ? ($data['ville_id'] ?? null) : null;

        // La gare choisie doit appartenir à la compagnie.
        if ($this->role === 'agent_gare') {
            // abort_unless(
            //     $this->gareId && Gare::whereKey($this->gareId)->where('compagnie_id', $this->compagnieId)->exists(),
            //     403
            // );
                abort_unless(
                $this->gareId && $authUser->garesGerables($this->compagnieId)->whereKey($this->gareId)->exists(),
                403
            );
        }

        unset($data['role'], $data['compagnie_id'], $data['gare_id'], $data['ville_id']);

        $data['telephone_verifie'] = true;
        // Mot de passe défini par l'administrateur : l'utilisateur devra le changer à sa première connexion.
        $data['doit_changer_mot_de_passe'] = true;

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        $record = parent::handleRecordCreation($data);

        $record->assignerRolePourCompagnie($this->role, $this->compagnieId, $this->gareId, $this->villeId);

        return $record;
    }
}