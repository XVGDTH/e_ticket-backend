<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateSuperAdmin extends Command
{
    protected $signature = 'app:create-super-admin';
    protected $description = 'Crée le premier compte super administrateur';

    public function handle(): int
    {
        $nom = $this->ask('Nom');
        $prenom = $this->ask('Prénom');
        $email = $this->ask('Email');
        $telephone = $this->ask('Téléphone');
        $password = $this->secret('Mot de passe');

        $user = User::create([
            'name' => "$prenom $nom",
            'prenom' => $prenom,
            'nom' => $nom,
            'email' => $email,
            'telephone' => $telephone,
            'telephone_verifie' => true,
            'password' => Hash::make($password),
        ]);

        $user->assignerRolePourCompagnie('super_admin', null);

        $this->info("Super admin créé : {$user->email}");

        return self::SUCCESS;
    }
}