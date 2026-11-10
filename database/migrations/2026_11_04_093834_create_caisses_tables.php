<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('caisses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('compagnie_id')->constrained('compagnies')->cascadeOnDelete();
            $t->foreignId('gare_id')->constrained('gares')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->timestamp('ouverte_le');
            $t->timestamp('fermee_le')->nullable();
            $t->unsignedInteger('fond_initial')->default(0);
            $t->bigInteger('especes_attendues')->nullable();
            $t->bigInteger('especes_comptees')->nullable();
            $t->bigInteger('ecart')->nullable();
            $t->string('statut', 12)->default('ouverte'); // ouverte | fermee | validee
            $t->string('note', 255)->nullable();
            $t->foreignId('valide_par')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('valide_le')->nullable();
            $t->timestamps();

            $t->index(['user_id', 'statut']);
            $t->index(['gare_id', 'ouverte_le']);
        });

        Schema::create('mouvements_caisse', function (Blueprint $t) {
            $t->id();
            $t->foreignId('caisse_id')->constrained('caisses')->cascadeOnDelete();
            $t->foreignId('compagnie_id')->constrained('compagnies')->cascadeOnDelete();
            $t->foreignId('gare_id')->constrained('gares')->cascadeOnDelete();
            $t->foreignId('billet_id')->nullable()->constrained('billets')->nullOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->string('type', 15);   // vente | annulation | remboursement | entree | sortie
            $t->string('mode', 20)->default('especes');
            $t->bigInteger('montant'); // positif = entrée d'argent, négatif = sortie
            $t->string('motif', 255)->nullable();
            $t->string('reference', 80)->nullable();
            $t->timestamp('created_at')->useCurrent();

            $t->index(['compagnie_id', 'created_at']);
            $t->index(['gare_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mouvements_caisse');
        Schema::dropIfExists('caisses');
    }
};
