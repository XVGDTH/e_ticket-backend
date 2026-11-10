<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('compagnie_id')->constrained('compagnies')->cascadeOnDelete();
            $t->foreignId('gare_id')->nullable()->constrained('gares')->nullOnDelete();
            $t->unsignedBigInteger('chauffeur_id')->nullable()->index();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('matricule', 30);
            $t->string('nom', 80);
            $t->string('prenoms', 120);
            $t->string('sexe', 1)->nullable();
            $t->date('date_naissance')->nullable();
            $t->string('lieu_naissance', 80)->nullable();
            $t->string('nationalite', 60)->default('Burkinabè');
            $t->string('cnib_numero', 40)->nullable()->index();
            $t->string('situation_familiale', 20)->default('celibataire');
            $t->boolean('conjoint_a_charge')->default(false);
            $t->unsignedTinyInteger('enfants_a_charge')->default(0);
            $t->string('telephone', 30)->nullable();
            $t->string('email', 120)->nullable();
            $t->string('adresse', 255)->nullable();
            $t->string('contact_urgence_nom', 120)->nullable();
            $t->string('contact_urgence_lien', 40)->nullable();
            $t->string('telephone_urgence', 30)->nullable();
            $t->string('photo')->nullable();
            $t->string('poste', 100);
            $t->string('service', 30)->default('exploitation');
            $t->string('statut', 20)->default('actif')->index();
            $t->date('date_embauche');
            $t->date('date_sortie')->nullable();
            $t->string('motif_sortie', 255)->nullable();
            $t->time('horaire_debut')->nullable();
            $t->time('horaire_fin')->nullable();
            $t->string('mode_paiement', 20)->default('especes');
            $t->string('numero_paiement', 60)->nullable();
            $t->string('cnss_numero', 40)->nullable();
            $t->text('observations')->nullable();
            $t->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();

            $t->unique(['compagnie_id', 'matricule']);
        });

        Schema::create('contrats_employes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employe_id')->constrained('employes')->cascadeOnDelete();
            $t->string('type', 20);
            $t->date('date_debut');
            $t->date('date_fin')->nullable();
            $t->date('fin_essai')->nullable();
            $t->string('categorie', 80)->nullable();
            $t->string('mode', 12)->default('mensuel'); // mensuel | journalier
            $t->unsignedBigInteger('salaire_base')->default(0);
            $t->unsignedSmallInteger('heures_hebdo')->default(40);
            $t->json('elements_fixes')->nullable();
            $t->string('fichier')->nullable();
            $t->boolean('actif')->default(true)->index();
            $t->string('motif_fin', 255)->nullable();
            $t->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('presences_employes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('compagnie_id')->constrained('compagnies')->cascadeOnDelete();
            $t->foreignId('gare_id')->nullable()->constrained('gares')->nullOnDelete();
            $t->foreignId('employe_id')->constrained('employes')->cascadeOnDelete();
            $t->unsignedBigInteger('conge_id')->nullable()->index();
            $t->date('date');
            $t->string('statut', 24)->default('present');
            $t->time('heure_arrivee')->nullable();
            $t->time('heure_depart')->nullable();
            $t->unsignedSmallInteger('minutes_retard')->default(0);
            $t->unsignedSmallInteger('heures_sup_minutes')->default(0);
            $t->string('note', 255)->nullable();
            $t->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();

            $t->unique(['employe_id', 'date']);
            $t->index(['compagnie_id', 'date']);
            $t->index(['gare_id', 'date']);
        });

        Schema::create('conges_employes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('compagnie_id')->constrained('compagnies')->cascadeOnDelete();
            $t->foreignId('employe_id')->constrained('employes')->cascadeOnDelete();
            $t->string('type', 30);
            $t->date('date_debut');
            $t->date('date_fin');
            $t->unsignedSmallInteger('jours')->default(1);
            $t->string('statut', 20)->default('demande')->index();
            $t->string('motif', 255)->nullable();
            $t->string('justificatif')->nullable();
            $t->foreignId('decide_par')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('decide_le')->nullable();
            $t->string('motif_refus', 255)->nullable();
            $t->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('avances_employes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('compagnie_id')->constrained('compagnies')->cascadeOnDelete();
            $t->foreignId('employe_id')->constrained('employes')->cascadeOnDelete();
            $t->string('type', 10)->default('avance'); // avance | pret
            $t->unsignedBigInteger('montant');
            $t->unsignedSmallInteger('nb_mensualites')->default(1);
            $t->unsignedBigInteger('mensualite')->default(0);
            $t->unsignedBigInteger('reste_du')->default(0);
            $t->date('date_octroi');
            $t->date('debut_remboursement')->nullable();
            $t->string('motif', 255)->nullable();
            $t->string('statut', 12)->default('en_cours')->index();
            $t->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('sanctions_employes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('compagnie_id')->constrained('compagnies')->cascadeOnDelete();
            $t->foreignId('employe_id')->constrained('employes')->cascadeOnDelete();
            $t->string('type', 30);
            $t->date('date_faits');
            $t->date('date_sanction');
            $t->unsignedSmallInteger('jours_mise_a_pied')->default(0);
            $t->text('motif');
            $t->text('notes')->nullable();
            $t->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('documents_employes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('compagnie_id')->constrained('compagnies')->cascadeOnDelete();
            $t->foreignId('employe_id')->constrained('employes')->cascadeOnDelete();
            $t->string('type', 30);
            $t->string('libelle', 120)->nullable();
            $t->string('fichier');
            $t->date('expire_le')->nullable();
            $t->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('periodes_paie', function (Blueprint $t) {
            $t->id();
            $t->foreignId('compagnie_id')->constrained('compagnies')->cascadeOnDelete();
            $t->unsignedSmallInteger('annee');
            $t->unsignedTinyInteger('mois');
            $t->string('statut', 12)->default('brouillon')->index(); // brouillon | validee | payee
            $t->timestamp('genere_le')->nullable();
            $t->foreignId('valide_par')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('valide_le')->nullable();
            $t->timestamp('paye_le')->nullable();
            $t->timestamps();

            $t->unique(['compagnie_id', 'annee', 'mois']);
        });

        Schema::create('bulletins_paie', function (Blueprint $t) {
            $t->id();
            $t->foreignId('periode_paie_id')->constrained('periodes_paie')->cascadeOnDelete();
            $t->foreignId('compagnie_id')->constrained('compagnies')->cascadeOnDelete();
            $t->foreignId('employe_id')->constrained('employes')->cascadeOnDelete();
            $t->foreignId('contrat_id')->nullable()->constrained('contrats_employes')->nullOnDelete();
            $t->unsignedSmallInteger('jours_travailles')->default(0);
            $t->unsignedSmallInteger('jours_non_payes')->default(0);
            $t->unsignedSmallInteger('minutes_retard')->default(0);
            $t->unsignedInteger('heures_sup_minutes')->default(0);
            $t->bigInteger('salaire_base')->default(0);
            $t->bigInteger('retenue_absences')->default(0);
            $t->bigInteger('primes_fixes')->default(0);
            $t->bigInteger('heures_sup_montant')->default(0);
            $t->bigInteger('primes_variables')->default(0);
            $t->bigInteger('brut')->default(0);
            $t->bigInteger('base_cnss')->default(0);
            $t->bigInteger('cnss_salarie')->default(0);
            $t->bigInteger('brut_imposable')->default(0);
            $t->bigInteger('iuts')->default(0);
            $t->bigInteger('retenue_avance')->default(0);
            $t->bigInteger('autres_retenues')->default(0);
            $t->bigInteger('net_a_payer')->default(0);
            $t->bigInteger('cnss_employeur')->default(0);
            $t->bigInteger('tpa')->default(0);
            $t->bigInteger('cout_employeur')->default(0);
            $t->string('statut_paiement', 12)->default('a_payer'); // a_payer | paye
            $t->timestamp('paye_le')->nullable();
            $t->string('mode_paiement', 20)->nullable();
            $t->string('reference_paiement', 80)->nullable();
            $t->string('observation', 255)->nullable();
            $t->json('detail')->nullable();
            $t->timestamps();

            $t->unique(['periode_paie_id', 'employe_id']);
        });

        Schema::create('remboursements_avances', function (Blueprint $t) {
            $t->id();
            $t->foreignId('avance_id')->constrained('avances_employes')->cascadeOnDelete();
            $t->foreignId('bulletin_id')->constrained('bulletins_paie')->cascadeOnDelete();
            $t->unsignedBigInteger('montant');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['remboursements_avances', 'bulletins_paie', 'periodes_paie', 'documents_employes', 'sanctions_employes',
            'avances_employes', 'conges_employes', 'presences_employes', 'contrats_employes', 'employes'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
