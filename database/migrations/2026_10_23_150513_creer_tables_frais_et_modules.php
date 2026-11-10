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
        Schema::create('frais_regles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compagnie_id')->nullable()->constrained('compagnies')->cascadeOnDelete();
            $table->foreignId('gare_id')->nullable()->constrained('gares')->cascadeOnDelete();
            $table->foreignId('route_transport_id')->nullable()->constrained('route_transports')->cascadeOnDelete();
            $table->string('trajet', 13)->nullable();          // national, international ou null (les deux)
            $table->unsignedInteger('prix_min')->default(0);   // prix du billet, par voyageur
            $table->unsignedInteger('prix_max')->nullable();   // null = sans limite
            $table->string('mode', 11)->default('fixe');       // fixe ou pourcentage
            $table->decimal('valeur', 8, 2);
            $table->unsignedInteger('frais_min')->nullable();
            $table->unsignedInteger('frais_max')->nullable();
            $table->boolean('actif')->default(true);
            $table->string('note', 120)->nullable();
            $table->timestamps();
            $table->index(['compagnie_id', 'gare_id', 'route_transport_id', 'actif'], 'frais_regles_portee_index');
        });

        Schema::create('modules_compagnies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compagnie_id')->constrained('compagnies')->cascadeOnDelete();
            $table->string('module', 30);
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->unique(['compagnie_id', 'module']);
        });

        Schema::create('modules_gares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gare_id')->constrained('gares')->cascadeOnDelete();
            $table->string('module', 30);
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->unique(['gare_id', 'module']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('modules_gares');
        Schema::dropIfExists('modules_compagnies');
        Schema::dropIfExists('frais_regles');
    }
};
