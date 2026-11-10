<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE billets MODIFY statut ENUM('valide','utilise','annule','rembourse','en_attente_paiement') NOT NULL DEFAULT 'valide'");
    }

    public function down(): void
    {
        // Un billet encore en attente n'a pas d'équivalent dans l'ancien enum : on l'annule
        DB::table('billets')->where('statut', 'en_attente_paiement')->update(['statut' => 'annule']);

        DB::statement("ALTER TABLE billets MODIFY statut ENUM('valide','utilise','annule','rembourse') NOT NULL DEFAULT 'valide'");
    }
};
