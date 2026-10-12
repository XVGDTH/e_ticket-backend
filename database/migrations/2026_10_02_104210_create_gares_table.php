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
       Schema::create('gares', function (Blueprint $table) {
    $table->id();
    $table->foreignId('compagnie_id')->constrained()->cascadeOnDelete();
    $table->foreignId('ville_id')->constrained()->cascadeOnDelete();
    $table->string('quartier');
    $table->decimal('latitude', 10, 7)->nullable();
    $table->decimal('longitude', 10, 7)->nullable();
    $table->string('telephone')->nullable();
    $table->text('adresse')->nullable();
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gares');
    }
};
