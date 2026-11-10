<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('colis')) {
            Schema::create('colis', function (Blueprint $table) {
                $table->id();
                $table->string('reference', 20)->unique();
                $table->string('type', 8)->default('colis');
                $table->foreignId('compagnie_id')->constrained('compagnies')->cascadeOnDelete();
                $table->foreignId('gare_origine_id')->constrained('gares')->cascadeOnDelete();
                $table->foreignId('gare_destination_id')->constrained('gares')->cascadeOnDelete();
                $table->string('expediteur_nom', 100);
                $table->string('expediteur_telephone', 30);
                $table->string('destinataire_nom', 100);
                $table->string('destinataire_telephone', 30);
                $table->string('nature', 30)->nullable();
                $table->text('description')->nullable();
                $table->unsignedSmallInteger('nombre_pieces')->default(1);
                $table->decimal('poids_kg', 8, 2)->nullable();
                $table->unsignedInteger('valeur_declaree')->nullable();
                $table->unsignedInteger('montant')->default(0);
                $table->string('paiement', 12)->default('expediteur');
                $table->timestamp('paye_le')->nullable();
                $table->string('statut', 12)->default('enregistre')->index();
                $table->string('code_retrait', 6);
                $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
                $table->string('remis_a', 100)->nullable();
                $table->timestamp('remis_le')->nullable();
                $table->boolean('code_verifie')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('colis_evenements')) {
            Schema::create('colis_evenements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('colis_id')->constrained('colis')->cascadeOnDelete();
                $table->string('statut', 12);
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->unsignedBigInteger('gare_id')->nullable();
                $table->string('note', 255)->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        // Les tables appartiennent à la migration d'origine : rien à défaire ici.
    }
};
