<?php

namespace App\Filament\Pages;

use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;

class ProfilStaff extends BaseEditProfile
{
    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('prenom')->label('Prénom')->disabled()->dehydrated(false),
            TextInput::make('nom')->label('Nom')->disabled()->dehydrated(false),
            TextInput::make('telephone')->label('Téléphone')->disabled()->dehydrated(false),
            $this->getPasswordFormComponent(),
            $this->getPasswordConfirmationFormComponent(),
            $this->getCurrentPasswordFormComponent(),
        ]);
    }

    /** Le nouveau mot de passe doit être différent de l'actuel. */
    protected function getPasswordFormComponent(): Component
    {
        return parent::getPasswordFormComponent()->different('currentPassword');
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Un nouveau mot de passe a été défini : l'obligation de le changer est levée.
        if (array_key_exists('password', $data)) {
            $data['doit_changer_mot_de_passe'] = false;
        }

        return $data;
    }

    protected function getRedirectUrl(): ?string
    {
        return Filament::getUrl();
    }
}