<?php

use App\Models\RouteTransport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sans distance, impossible de calculer passagers-km, sièges-km et carburant standard.
        $routes = (new RouteTransport)->getTable();

        if (! Schema::hasColumn($routes, 'distance_km')) {
            Schema::table($routes, function (Blueprint $table) {
                $table->unsignedInteger('distance_km')->nullable();
            });
        }

        if (! Schema::hasTable('cg_parametres')) {
            Schema::create('cg_parametres', function (Blueprint $table) {
                $table->id();
                $table->foreignId('compagnie_id')->unique()->constrained()->cascadeOnDelete();
                $table->decimal('norme_l100', 5, 2)->nullable();
                $table->unsignedInteger('prix_litre')->nullable();
                $table->decimal('ecart_seuil_pct', 5, 2)->nullable();
                $table->decimal('commission_paiement_pct', 5, 2)->default(0);
                $table->timestamps();
            });
        }

        // Coûts de gare, d'agence, de siège et d'atelier, répartis ensuite sur les départs.
        $chargesIndex = 'cg_charges_compagnie_periode_idx';

        if (! Schema::hasTable('cg_charges_structure')) {
            Schema::create('cg_charges_structure', function (Blueprint $table) use ($chargesIndex) {
                $table->id();
                $table->foreignId('compagnie_id')->constrained()->cascadeOnDelete();
                $table->foreignId('gare_id')->nullable()->constrained()->nullOnDelete();
                $table->string('niveau', 10);              // gare, siege, atelier
                $table->string('libelle');
                $table->unsignedBigInteger('montant');
                $table->date('periode_debut');
                $table->date('periode_fin');
                $table->string('cle', 12)->default('departs'); // departs, voyageurs, km, recettes
                $table->text('note')->nullable();
                $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['compagnie_id', 'periode_debut', 'periode_fin'], $chargesIndex);
            });
        } elseif (! Schema::hasIndex('cg_charges_structure', $chargesIndex)) {
            Schema::table('cg_charges_structure', function (Blueprint $table) use ($chargesIndex) {
                $table->index(['compagnie_id', 'periode_debut', 'periode_fin'], $chargesIndex);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cg_charges_structure');
        Schema::dropIfExists('cg_parametres');

        $routes = (new RouteTransport)->getTable();

        if (Schema::hasColumn($routes, 'distance_km')) {
            Schema::table($routes, fn (Blueprint $table) => $table->dropColumn('distance_km'));
        }
    }
};
