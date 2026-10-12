<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billets', function (Blueprint $table) {
            $table->enum('statut', ['valide', 'utilise', 'annule', 'rembourse'])
                ->default('valide')->after('date_paiement');
            $table->index(['gare_id', 'statut', 'date_voyage'], 'billets_gare_statut_date_index');
        });

        // Historique : qui a fait quoi, quand, depuis où.
        Schema::create('billet_evenements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('billet_id')->constrained()->cascadeOnDelete();
            $table->string('statut_avant', 20)->nullable();
            $table->string('statut_apres', 20);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('motif', 255)->nullable();
            $table->string('source', 20)->default('panel'); // achat | panel | api_agent
            $table->timestamp('created_at')->useCurrent();
            $table->index(['billet_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billet_evenements');

        Schema::table('billets', function (Blueprint $table) {
            $table->dropIndex('billets_gare_statut_date_index');
            $table->dropColumn('statut');
        });
    }
};