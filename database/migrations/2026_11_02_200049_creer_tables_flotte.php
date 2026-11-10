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
        Schema::create('vehicules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compagnie_id')->constrained('compagnies')->cascadeOnDelete();
            $table->foreignId('gare_id')->nullable()->constrained('gares')->nullOnDelete();
            $table->string('immatriculation', 20);
            $table->string('numero_parc', 20)->nullable();
            $table->string('marque', 40)->nullable();
            $table->string('modele', 40)->nullable();
            $table->unsignedSmallInteger('annee')->nullable();
            $table->unsignedSmallInteger('places');
            $table->string('carburant', 10)->default('gasoil');
            $table->unsignedInteger('km_actuel')->default(0);
            $table->string('statut', 12)->default('actif'); // actif, maintenance, panne, reforme
            $table->date('date_acquisition')->nullable();
            $table->date('date_mise_en_service')->nullable();
            $table->unsignedBigInteger('prix_acquisition')->nullable();
            $table->unsignedTinyInteger('duree_amortissement_ans')->nullable();
            $table->unsignedBigInteger('valeur_residuelle')->default(0);
            $table->date('assurance_expire_le')->nullable();
            $table->date('visite_technique_expire_le')->nullable();
            $table->string('autre_doc_libelle', 60)->nullable();
            $table->date('autre_doc_expire_le')->nullable();
            $table->unsignedInteger('km_prochaine_vidange')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->unique(['compagnie_id', 'immatriculation']);
        });

        Schema::create('chauffeurs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compagnie_id')->constrained('compagnies')->cascadeOnDelete();
            $table->foreignId('gare_id')->nullable()->constrained('gares')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('prenom', 100);
            $table->string('nom', 100);
            $table->string('telephone', 30)->nullable();
            $table->string('permis_numero', 40)->nullable();
            $table->string('permis_categorie', 10)->nullable();
            $table->date('permis_expire_le')->nullable();
            $table->date('date_embauche')->nullable();
            $table->string('remuneration_type', 10)->default('par_voyage'); // par_voyage ou mensuel
            $table->unsignedInteger('remuneration_montant')->default(0);
            $table->foreignId('vehicule_habituel_id')->nullable()->constrained('vehicules')->nullOnDelete();
            $table->boolean('actif')->default(true);
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('voyages_cars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compagnie_id')->constrained('compagnies')->cascadeOnDelete();
            $table->foreignId('route_transport_id')->constrained('route_transports');
            $table->foreignId('gare_id')->constrained('gares');
            $table->date('date_voyage');
            $table->string('heure', 5); // HH:MM
            $table->foreignId('vehicule_id')->constrained('vehicules');
            $table->foreignId('chauffeur_id')->nullable()->constrained('chauffeurs')->nullOnDelete();
            $table->string('statut', 10)->default('programme'); // programme, parti, termine, annule
            $table->unsignedInteger('km_parcourus')->nullable();
            $table->unsignedSmallInteger('places_guichet')->default(0);
            $table->unsignedInteger('recette_guichet')->default(0);
            $table->unsignedInteger('recette_colis')->default(0);
            $table->text('observations')->nullable();
            $table->timestamp('cloture_le')->nullable();
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['route_transport_id', 'date_voyage', 'heure', 'vehicule_id'], 'voyages_cars_depart_car_unique');
            $table->index(['compagnie_id', 'date_voyage']);
        });

        Schema::create('depenses_vehicules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compagnie_id')->constrained('compagnies')->cascadeOnDelete();
            $table->foreignId('vehicule_id')->constrained('vehicules');
            $table->foreignId('voyage_id')->nullable()->constrained('voyages_cars')->nullOnDelete();
            $table->date('date_depense');
            $table->string('categorie', 20);
            $table->unsignedInteger('montant');
            $table->decimal('litres', 8, 2)->nullable();
            $table->unsignedInteger('kilometrage')->nullable();
            $table->date('couvre_du')->nullable();
            $table->date('couvre_au')->nullable();
            $table->string('fournisseur', 100)->nullable();
            $table->string('reference', 60)->nullable();
            $table->string('note', 255)->nullable();
            $table->string('piece')->nullable();
            $table->string('source', 10)->default('saisie'); // saisie ou cloture
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['vehicule_id', 'date_depense']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('depenses_vehicules');
        Schema::dropIfExists('voyages_cars');
        Schema::dropIfExists('chauffeurs');
        Schema::dropIfExists('vehicules');
    }
};
