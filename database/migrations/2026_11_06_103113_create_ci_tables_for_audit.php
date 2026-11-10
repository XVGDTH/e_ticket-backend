<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ci_controles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compagnie_id')->constrained('compagnies')->cascadeOnDelete();
            $table->string('titre', 150);
            $table->text('objectif');
            $table->text('procedure')->nullable();
            $table->string('frequence', 20);
            $table->string('niveau_risque', 12)->default('moyen');
            $table->foreignId('responsable_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('prochaine_echeance')->nullable();
            $table->boolean('actif')->default(true);
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['compagnie_id', 'actif', 'prochaine_echeance'], 'ci_ctrl_company_active_due_idx');
        });

        Schema::create('ci_recommandations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compagnie_id')->constrained('compagnies')->cascadeOnDelete();
            $table->foreignId('controle_id')->nullable()->constrained('ci_controles')->nullOnDelete();
            $table->string('titre', 150);
            $table->text('constat');
            $table->text('action_requise');
            $table->string('priorite', 12)->default('normale');
            $table->string('statut', 15)->default('ouverte');
            $table->foreignId('responsable_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('echeance')->nullable();
            $table->text('resolution')->nullable();
            $table->timestamp('terminee_le')->nullable();
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['compagnie_id', 'statut', 'echeance'], 'ci_reco_company_status_due_idx');
            $table->index(['compagnie_id', 'controle_id'], 'ci_reco_company_control_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ci_recommandations');
        Schema::dropIfExists('ci_controles');
    }
};
