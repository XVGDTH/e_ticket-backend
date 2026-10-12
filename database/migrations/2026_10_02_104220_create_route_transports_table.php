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
        Schema::create('route_transports', function (Blueprint $table) {
    $table->id();
    $table->foreignId('compagnie_id')->constrained()->cascadeOnDelete();
    $table->foreignId('gare_depart_id')->constrained('gares')->cascadeOnDelete();
    $table->foreignId('ville_arrivee_id')->constrained('villes')->cascadeOnDelete();
    $table->unsignedInteger('prix');
    $table->string('duree_max')->nullable();
    $table->boolean('actif')->default(true);
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('route_transports');
    }
};
