<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Mot de passe temporaire : à changer à la première connexion.
            $table->boolean('doit_changer_mot_de_passe')->default(false);

            // Double authentification par application (Google Authenticator, Authy...).
            $table->text('app_authentication_secret')->nullable();
            $table->text('app_authentication_recovery_codes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['doit_changer_mot_de_passe', 'app_authentication_secret', 'app_authentication_recovery_codes']);
        });
    }
};