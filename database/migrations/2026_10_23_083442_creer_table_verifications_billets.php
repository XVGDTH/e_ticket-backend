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
        Schema::create('verifications_billets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('billet_id')->nullable()->constrained('billets')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('gare_id')->nullable()->index();
            $table->string('mode', 8);        // qr ou manuel
            $table->string('resultat', 20)->index();
            $table->string('contenu', 120)->nullable(); // conservé seulement pour les codes non reconnus
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('verifications_billets');
    }
};
