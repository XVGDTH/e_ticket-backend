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
        Schema::create('horaires', function (Blueprint $table) {
    $table->id();
    $table->foreignId('route_transport_id')->constrained()->cascadeOnDelete();
    $table->time('heure');
    $table->json('jours_semaine')->nullable(); // null = tous les jours
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('horaires');
    }
};
