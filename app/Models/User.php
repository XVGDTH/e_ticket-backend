<?php

namespace App\Models;

use App\Models\Concerns\HasCompagnieRoles;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthentication;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable([
    'prenom',
    'nom',
    'telephone',
    'telephone_verifie',
    'actif',
    'doit_changer_mot_de_passe',
    'password',
])]
#[Hidden([
    'password',
    'remember_token',
    'app_authentication_secret',
    'app_authentication_recovery_codes',
])]
class User extends Authenticatable implements FilamentUser, HasName, HasAppAuthentication, HasAppAuthenticationRecovery
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, HasCompagnieRoles, HasRoles;
    use InteractsWithAppAuthentication;
    use InteractsWithAppAuthenticationRecovery;

    protected function casts(): array
    {
        return [
            'telephone_verifie' => 'boolean',
            'actif' => 'boolean',
            'doit_changer_mot_de_passe' => 'boolean',
            'password' => 'hashed',
            'app_authentication_secret' => 'encrypted',
            'app_authentication_recovery_codes' => 'encrypted:array',
        ];
    }

    public function billets()
    {
        return $this->hasMany(\App\Models\Billet::class);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        // Un compte désactivé n'entre plus, même s'il a encore un rôle.
        if (! ($this->actif ?? true)) {
            return false;
        }

        return $this->isSuperAdmin() || $this->isAdminCompagnie() || $this->isAgentGare();
    }

    public function getFilamentName(): string
    {
        return trim("{$this->prenom} {$this->nom}") ?: 'Utilisateur';
    }

    /** Nom affiché dans l'application d'authentification : tes utilisateurs n'ont pas d'e-mail. */
    public function getAppAuthenticationHolderName(): string
    {
        return (string) $this->telephone;
    }

    /**
     * Ce que l'utilisateur doit faire avant d'utiliser le panel (null = rien).
     */
    public function exigenceSecurite(): ?string
    {
        if ($this->doit_changer_mot_de_passe) {
            return 'Vous devez choisir un nouveau mot de passe avant de continuer.';
        }

        if (
            config('securite.mfa_obligatoire_admins')
            && ($this->isSuperAdmin() || $this->isAdminCompagnie())
            && blank($this->getAppAuthenticationSecret())
        ) {
            return "Activez la double authentification (application d'authentification) pour continuer.";
        }

        return null;
    }
}