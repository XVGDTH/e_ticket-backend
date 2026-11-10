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
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('marketing_whatsapp')->default(false);
            $table->timestamp('marketing_whatsapp_le')->nullable();
        });

        Schema::create('marketing_consentements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('accepte');
            $table->string('source', 24);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('preferences_clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('compagnie_id')->constrained('compagnies')->cascadeOnDelete();
            $table->unsignedBigInteger('gare_id')->default(0); // 0 = l'ensemble de la compagnie
            $table->unsignedInteger('nb_billets')->default(0);
            $table->unsignedInteger('nb_voyageurs')->default(0);
            $table->bigInteger('montant')->default(0);
            $table->date('premier_achat')->nullable();
            $table->date('dernier_achat')->nullable();
            $table->decimal('score', 5, 1)->default(0);
            $table->unsignedTinyInteger('rang')->nullable();
            $table->string('segment', 12)->default('occasionnel');
            $table->timestamps();
            $table->unique(['user_id', 'compagnie_id', 'gare_id'], 'pref_unique');
            $table->index(['compagnie_id', 'gare_id', 'segment'], 'pref_segment');
        });

        Schema::create('marketing_modeles', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 80);
            $table->string('nom_meta', 120)->unique();
            $table->string('langue', 5)->default('fr');
            $table->text('corps');
            $table->string('pied', 60)->nullable();
            $table->boolean('bouton_stop')->default(true);
            $table->json('exemples')->nullable();
            $table->string('statut', 12)->default('brouillon'); // brouillon, en_attente, approuve, refuse, pause
            $table->string('meta_id', 40)->nullable()->index();
            $table->string('motif_refus', 255)->nullable();
            $table->timestamp('soumis_le')->nullable();
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('marketing_campagnes', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 100);
            $table->foreignId('modele_id')->constrained('marketing_modeles');
            $table->foreignId('compagnie_id')->constrained('compagnies');
            $table->unsignedBigInteger('gare_id')->nullable();
            $table->json('segments');
            $table->json('variables')->nullable();
            $table->string('statut', 12)->default('brouillon'); // brouillon, planifiee, en_cours, terminee, annulee, erreur
            $table->timestamp('planifiee_le')->nullable();
            $table->timestamp('lancee_le')->nullable();
            $table->timestamp('terminee_le')->nullable();
            $table->unsignedInteger('nb_cibles')->default(0);
            $table->string('erreur', 255)->nullable();
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('marketing_destinataires', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campagne_id')->constrained('marketing_campagnes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('telephone', 20);
            $table->string('statut', 10)->default('en_attente'); // en_attente, envoye, remis, lu, echec, ignore
            $table->string('wamid', 120)->nullable()->index();
            $table->string('erreur_code', 12)->nullable();
            $table->string('erreur', 255)->nullable();
            $table->timestamp('envoye_le')->nullable();
            $table->timestamp('remis_le')->nullable();
            $table->timestamp('lu_le')->nullable();
            $table->timestamps();
            $table->unique(['campagne_id', 'user_id']);
            $table->index(['statut', 'envoye_le']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketing_destinataires');
        Schema::dropIfExists('marketing_campagnes');
        Schema::dropIfExists('marketing_modeles');
        Schema::dropIfExists('preferences_clients');
        Schema::dropIfExists('marketing_consentements');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['marketing_whatsapp', 'marketing_whatsapp_le']);
        });
    }
};
