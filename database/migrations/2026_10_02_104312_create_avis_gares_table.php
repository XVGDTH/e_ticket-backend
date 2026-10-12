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
        Schema::create('avis_gares', function (Blueprint $table) {
    $table->id();
    $table->foreignId('billet_id')->constrained()->cascadeOnDelete();
    $table->foreignId('gare_id')->constrained();
    $table->json('scores');
    $table->text('commentaire')->nullable();
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('avis_gares');
    }
};
