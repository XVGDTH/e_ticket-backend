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
        Schema::create('cpt_categories_immobilisations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compagnie_id')->constrained('compagnies')->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('libelle', 120);
            $table->unsignedSmallInteger('duree_utilite_mois')->nullable();
            $table->string('mode_amortissement', 16)->default('lineaire');
            $table->string('premiere_periode', 24)->default('prorata_temporis');
            $table->foreignId('compte_actif_id')->nullable()->constrained('cpt_comptes')->restrictOnDelete();
            $table->foreignId('compte_amortissement_id')->nullable()->constrained('cpt_comptes')->restrictOnDelete();
            $table->foreignId('compte_charge_id')->nullable()->constrained('cpt_comptes')->restrictOnDelete();
            $table->boolean('actif')->default(false);
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['compagnie_id', 'code'], 'cpt_cat_immo_cie_code_unique');
        });

        Schema::create('cpt_immobilisations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compagnie_id')->constrained('compagnies')->cascadeOnDelete();
            $table->foreignId('categorie_id')->constrained('cpt_categories_immobilisations')->restrictOnDelete();
            $table->string('code', 30);
            $table->string('designation', 160);
            $table->text('description')->nullable();
            $table->date('date_acquisition');
            $table->date('date_mise_en_service');
            $table->unsignedBigInteger('cout_acquisition');
            $table->unsignedBigInteger('valeur_residuelle')->default(0);
            $table->unsignedSmallInteger('duree_utilite_mois');
            $table->string('mode_amortissement', 16);
            $table->string('premiere_periode', 24);
            $table->foreignId('compte_actif_id')->constrained('cpt_comptes')->restrictOnDelete();
            $table->foreignId('compte_amortissement_id')->constrained('cpt_comptes')->restrictOnDelete();
            $table->foreignId('compte_charge_id')->constrained('cpt_comptes')->restrictOnDelete();
            $table->string('statut', 20)->default('brouillon');
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('valide_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validee_le')->nullable();
            $table->timestamps();
            $table->unique(['compagnie_id', 'code'], 'cpt_immo_cie_code_unique');
            $table->index(['compagnie_id', 'statut', 'date_mise_en_service'], 'cpt_immo_cie_statut_date_idx');
        });

        Schema::create('cpt_amortissements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compagnie_id')->constrained('compagnies')->cascadeOnDelete();
            $table->foreignId('immobilisation_id')->constrained('cpt_immobilisations')->restrictOnDelete();
            $table->date('periode_debut');
            $table->date('periode_fin');
            $table->unsignedBigInteger('montant');
            $table->string('statut', 20)->default('planifie');
            $table->foreignId('ecriture_id')->nullable()->unique()->constrained('cpt_ecritures')->nullOnDelete();
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('valide_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validee_le')->nullable();
            $table->timestamps();
            $table->unique(['immobilisation_id', 'periode_fin'], 'cpt_amort_asset_period_unique');
            $table->index(['compagnie_id', 'statut', 'periode_fin'], 'cpt_amort_cie_statut_period_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cpt_amortissements');
        Schema::dropIfExists('cpt_immobilisations');
        Schema::dropIfExists('cpt_categories_immobilisations');
    }
};
