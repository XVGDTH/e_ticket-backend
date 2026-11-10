<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cg_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compagnie_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('annee');
            $table->unsignedSmallInteger('version');
            $table->string('libelle', 120);
            $table->string('statut', 10)->default('brouillon'); // brouillon, valide
            $table->decimal('remplissage_cible_pct', 5, 2);
            $table->unsignedInteger('cout_km_maintenance')->default(0);
            $table->unsignedInteger('cout_km_pneus')->default(0);
            $table->unsignedInteger('frais_route_depart')->default(0);
            $table->unsignedInteger('cout_horaire_equipage')->default(0);
            $table->unsignedBigInteger('fixes_car_mensuel')->default(0);
            $table->decimal('norme_l100', 5, 2);
            $table->unsignedInteger('prix_litre');
            $table->decimal('commission_paiement_pct', 5, 2)->default(0);
            $table->text('note')->nullable();
            $table->timestamp('valide_le')->nullable();
            $table->foreignId('valide_par')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['compagnie_id', 'annee', 'version']);
        });

        Schema::create('cg_budget_lignes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained('cg_budgets')->cascadeOnDelete();
            $table->unsignedTinyInteger('mois');
            $table->unsignedBigInteger('route_transport_id');
            $table->unsignedInteger('departs')->default(0);
            $table->unsignedInteger('km')->default(0);
            $table->unsignedInteger('sieges')->default(0);
            $table->unsignedInteger('pax')->default(0);
            foreach (['recettes', 'commissions', 'carburant', 'equipage', 'maintenance', 'pneus', 'frais_route', 'fixes_car', 'gare', 'siege'] as $c) {
                $table->unsignedBigInteger($c)->default(0);
            }

            $table->index(['budget_id', 'mois']);
        });

        Schema::create('cg_commentaires', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compagnie_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('annee');
            $table->unsignedTinyInteger('mois');
            $table->string('cle', 30); // « total » ou « ligne:ID »
            $table->text('texte');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['compagnie_id', 'annee', 'mois', 'cle']);
        });

        Schema::create('cg_clotures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compagnie_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('annee');
            $table->unsignedTinyInteger('mois');
            $table->longText('snapshot'); // chiffres arrêtés du mois, en JSON
            $table->foreignId('cloture_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cloture_le');
            $table->timestamps();

            $table->unique(['compagnie_id', 'annee', 'mois']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cg_clotures');
        Schema::dropIfExists('cg_commentaires');
        Schema::dropIfExists('cg_budget_lignes');
        Schema::dropIfExists('cg_budgets');
    }
};
