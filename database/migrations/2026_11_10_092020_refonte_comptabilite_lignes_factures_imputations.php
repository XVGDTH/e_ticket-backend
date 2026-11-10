<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Validation ligne par ligne
        Schema::table('cpt_lignes', function (Blueprint $table) {
            $table->timestamp('valide_le')->nullable()->after('credit');
            $table->foreignId('valide_par')->nullable()->after('valide_le')
                ->constrained('users')->nullOnDelete();
            $table->index(['ecriture_id', 'valide_le']);
        });

        // 2. Numéro de facture propre + code plateforme kmaa aléatoire
        Schema::table('cpt_ecritures', function (Blueprint $table) {
            $table->string('numero_facture', 30)->nullable()->after('reference');
            $table->string('code_kmaa', 20)->nullable()->unique()->after('numero_facture');
            $table->index(['compagnie_id', 'numero_facture']);
        });

        // Compteur de factures par exercice (même logique que dernier_numero des journaux)
        Schema::table('cpt_exercices', function (Blueprint $table) {
            $table->unsignedInteger('dernier_numero_facture')->default(0);
        });

        // 3. Imputations analytiques : une ligne peut être ventilée sur plusieurs sections
        Schema::create('cpt_imputations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compagnie_id')->constrained('compagnies')->cascadeOnDelete();
            $table->foreignId('ligne_id')->constrained('cpt_lignes')->cascadeOnDelete();
            $table->foreignId('section_id')->constrained('cpt_sections')->restrictOnDelete();
            $table->unsignedBigInteger('montant');            // FCFA, entier
            $table->decimal('pourcentage', 5, 2)->nullable(); // information, le montant fait foi
            $table->string('note', 160)->nullable();
            $table->timestamps();

            $table->index(['compagnie_id', 'section_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cpt_imputations');

        Schema::table('cpt_exercices', fn (Blueprint $t) => $t->dropColumn('dernier_numero_facture'));

        Schema::table('cpt_ecritures', function (Blueprint $t) {
            $t->dropIndex(['compagnie_id', 'numero_facture']);
            $t->dropUnique(['code_kmaa']);
            $t->dropColumn(['numero_facture', 'code_kmaa']);
        });

        Schema::table('cpt_lignes', function (Blueprint $t) {
            $t->dropIndex(['ecriture_id', 'valide_le']);
            $t->dropConstrainedForeignId('valide_par');
            $t->dropColumn('valide_le');
        });
    }
};
