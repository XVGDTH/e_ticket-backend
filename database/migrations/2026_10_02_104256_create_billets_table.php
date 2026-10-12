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
        Schema::create('billets', function (Blueprint $table) {
    $table->id();
    $table->string('reference')->unique();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('route_transport_id')->constrained();
    $table->foreignId('gare_id')->constrained();
    $table->date('date_voyage');
    $table->string('heure');
    $table->unsignedInteger('travelers_count');
    $table->unsignedInteger('montant_transport');
    $table->unsignedInteger('taxe');
    $table->string('operateur_paiement');
    $table->json('voyageurs'); // [{nom, code}]
    $table->timestamp('date_paiement');
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billets');
    }
};
