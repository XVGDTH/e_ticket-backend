<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ci_sequences_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compagnie_id')->constrained('compagnies')->cascadeOnDelete();
            $table->string('serie', 8);
            $table->unsignedSmallInteger('annee');
            $table->unsignedInteger('dernier_numero')->default(0);
            $table->timestamps();

            $table->unique(['compagnie_id', 'serie', 'annee'], 'ci_seq_company_series_year_uq');
        });

        Schema::create('ci_fournisseurs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compagnie_id')->constrained('compagnies')->cascadeOnDelete();
            $table->string('nom', 150);
            $table->string('identifiant_fiscal', 80)->nullable();
            $table->string('telephone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('adresse', 255)->nullable();
            $table->boolean('actif')->default(true);
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cree_par_nom', 150);
            $table->timestamps();

            $table->index(['compagnie_id', 'actif', 'nom'], 'ci_fournisseur_company_active_idx');
        });

        Schema::create('ci_articles_stock', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compagnie_id')->constrained('compagnies')->cascadeOnDelete();
            $table->string('reference', 50)->nullable();
            $table->string('nom', 150);
            $table->string('unite', 30);
            $table->decimal('quantite_stock', 12, 3)->default(0);
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['compagnie_id', 'reference'], 'ci_article_company_ref_uq');
            $table->index(['compagnie_id', 'nom'], 'ci_article_company_name_idx');
        });

        Schema::create('ci_receptions_fournisseurs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compagnie_id')->constrained('compagnies')->cascadeOnDelete();
            $table->foreignId('fournisseur_id')->constrained('ci_fournisseurs')->restrictOnDelete();
            $table->string('numero', 24);
            $table->date('recu_le');
            $table->text('description')->nullable();
            $table->string('statut', 20)->default('en_attente');
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cree_par_nom', 150);
            $table->foreignId('valide_par')->nullable()->constrained('users')->nullOnDelete();
            $table->string('valide_par_nom', 150)->nullable();
            $table->timestamp('valide_le')->nullable();
            $table->timestamps();

            $table->unique(['compagnie_id', 'numero'], 'ci_reception_company_number_uq');
            $table->index(['compagnie_id', 'statut', 'recu_le'], 'ci_reception_company_status_date_idx');
        });

        Schema::create('ci_lignes_reception_fournisseur', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reception_id')->constrained('ci_receptions_fournisseurs')->cascadeOnDelete();
            $table->foreignId('article_id')->constrained('ci_articles_stock')->restrictOnDelete();
            $table->decimal('quantite', 12, 3);
            $table->timestamps();

            $table->unique(['reception_id', 'article_id'], 'ci_ligne_reception_article_uq');
        });

        Schema::create('ci_mouvements_stock', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compagnie_id')->constrained('compagnies')->cascadeOnDelete();
            $table->foreignId('article_id')->constrained('ci_articles_stock')->restrictOnDelete();
            $table->foreignId('reception_id')->constrained('ci_receptions_fournisseurs')->restrictOnDelete();
            $table->foreignId('ligne_reception_id')->unique('ci_mvt_reception_line_uq')
                ->constrained('ci_lignes_reception_fournisseur')->restrictOnDelete();
            $table->string('type', 12);
            $table->decimal('quantite', 12, 3);
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cree_par_nom', 150);
            $table->timestamp('cree_le');
            $table->timestamps();

            $table->index(['compagnie_id', 'article_id', 'cree_le'], 'ci_mvt_company_article_date_idx');
        });

        Schema::create('ci_factures_fournisseurs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compagnie_id')->constrained('compagnies')->cascadeOnDelete();
            $table->foreignId('fournisseur_id')->constrained('ci_fournisseurs')->restrictOnDelete();
            $table->foreignId('reception_id')->constrained('ci_receptions_fournisseurs')->restrictOnDelete();
            $table->string('numero', 24);
            $table->string('reference_fournisseur', 100);
            $table->date('date_facture');
            $table->date('echeance')->nullable();
            $table->unsignedBigInteger('montant');
            $table->string('piece_path', 500)->nullable();
            $table->string('statut', 20)->default('en_attente');
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cree_par_nom', 150);
            $table->foreignId('valide_par')->nullable()->constrained('users')->nullOnDelete();
            $table->string('valide_par_nom', 150)->nullable();
            $table->timestamp('valide_le')->nullable();
            $table->timestamps();

            $table->unique(['compagnie_id', 'numero'], 'ci_facture_company_number_uq');
            $table->unique(['compagnie_id', 'fournisseur_id', 'reference_fournisseur'], 'ci_facture_supplier_ref_uq');
            $table->index(['compagnie_id', 'statut', 'date_facture'], 'ci_facture_company_status_date_idx');
        });

        Schema::create('ci_paiements_fournisseurs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compagnie_id')->constrained('compagnies')->cascadeOnDelete();
            $table->foreignId('facture_id')->constrained('ci_factures_fournisseurs')->restrictOnDelete();
            $table->string('numero', 24);
            $table->unsignedBigInteger('montant');
            $table->date('paye_le');
            $table->string('mode', 20);
            $table->string('reference', 100)->nullable();
            $table->string('statut', 20)->default('en_attente');
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cree_par_nom', 150);
            $table->foreignId('valide_par')->nullable()->constrained('users')->nullOnDelete();
            $table->string('valide_par_nom', 150)->nullable();
            $table->timestamp('valide_le')->nullable();
            $table->timestamps();

            $table->unique(['compagnie_id', 'numero'], 'ci_paiement_company_number_uq');
            $table->index(['compagnie_id', 'statut', 'paye_le'], 'ci_paiement_company_status_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ci_paiements_fournisseurs');
        Schema::dropIfExists('ci_factures_fournisseurs');
        Schema::dropIfExists('ci_mouvements_stock');
        Schema::dropIfExists('ci_lignes_reception_fournisseur');
        Schema::dropIfExists('ci_receptions_fournisseurs');
        Schema::dropIfExists('ci_articles_stock');
        Schema::dropIfExists('ci_fournisseurs');
        Schema::dropIfExists('ci_sequences_documents');
    }
};
