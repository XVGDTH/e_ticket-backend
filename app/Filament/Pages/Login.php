<?php

namespace App\Filament\Pages;

use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\ValidationException;

/**
 * Connexion par numéro de téléphone.
 * La limitation des tentatives, la protection contre le chronométrage et la double
 * authentification sont assurées par la page de connexion de Filament.
 */
class Login extends BaseLogin
{
    public function getHeading(): string|Htmlable
    {
        return 'Connexion';
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('telephone')
                ->label('Téléphone')
                ->required()
                ->autocomplete('username')
                ->autofocus(),
            TextInput::make('password')
                ->label('Mot de passe')
                ->password()
                ->revealable()
                ->autocomplete('current-password')
                ->required(),
            Checkbox::make('remember')
                ->label('Se souvenir de moi'),
        ]);
    }

    protected function getCredentialsFromFormData(array $data): array
    {
        return [
            'telephone' => trim((string) $data['telephone']),
            'password' => $data['password'],
        ];
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.telephone' => 'Numéro de téléphone ou mot de passe incorrect.',
        ]);
    }
}