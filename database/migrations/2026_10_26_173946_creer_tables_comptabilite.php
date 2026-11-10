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
        Schema::create('cpt_exercices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compagnie_id')->constrained('compagnies')->cascadeOnDelete();
            $table->string('libelle', 40);
            $table->date('date_debut');
            $table->date('date_fin');
            $table->boolean('cloture')->default(false);
            $table->timestamps();
            $table->unique(['compagnie_id', 'date_debut']);
        });

        Schema::create('cpt_comptes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compagnie_id')->nullable()->constrained('compagnies')->cascadeOnDelete(); // null = plan de base
            $table->string('numero', 10);
            $table->string('intitule', 160);
            $table->unsignedTinyInteger('classe');
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->index(['compagnie_id', 'numero']);
        });

        Schema::create('cpt_journaux', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compagnie_id')->constrained('compagnies')->cascadeOnDelete();
            $table->string('code', 5);
            $table->string('libelle', 60);
            $table->string('type', 12);
            $table->unsignedInteger('dernier_numero')->default(0);
            $table->timestamps();
            $table->unique(['compagnie_id', 'code']);
        });

        Schema::create('cpt_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compagnie_id')->constrained('compagnies')->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('libelle', 100);
            $table->string('type', 12)->default('activite'); // activite, vehicule, gare, autre
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->unique(['compagnie_id', 'code']);
        });

        Schema::create('cpt_ecritures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compagnie_id')->constrained('compagnies')->cascadeOnDelete();
            $table->foreignId('exercice_id')->constrained('cpt_exercices');
            $table->foreignId('journal_id')->constrained('cpt_journaux');
            $table->string('numero', 30)->unique();
            $table->date('date_ecriture');
            $table->string('libelle', 160);
            $table->string('reference', 60)->nullable();
            $table->unsignedBigInteger('gare_id')->nullable();
            $table->json('pieces')->nullable();
            $table->string('statut', 12)->default('brouillon'); // brouillon ou comptabilise
            $table->unsignedBigInteger('contrepasse_de')->nullable();
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('comptabilise_le')->nullable();
            $table->timestamps();
            $table->index(['compagnie_id', 'exercice_id', 'statut']);
        });

        Schema::create('cpt_lignes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ecriture_id')->constrained('cpt_ecritures')->cascadeOnDelete();
            $table->foreignId('compte_id')->constrained('cpt_comptes');
            $table->string('libelle', 160)->nullable();
            $table->bigInteger('debit')->default(0);
            $table->bigInteger('credit')->default(0);
            $table->foreignId('section_id')->nullable()->constrained('cpt_sections')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cpt_lignes');
        Schema::dropIfExists('cpt_ecritures');
        Schema::dropIfExists('cpt_sections');
        Schema::dropIfExists('cpt_journaux');
        Schema::dropIfExists('cpt_comptes');
        Schema::dropIfExists('cpt_exercices');
    }
};
