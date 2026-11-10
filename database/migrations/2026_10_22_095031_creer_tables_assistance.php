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
        Schema::create('demandes_assistance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('billet_id')->nullable()->constrained('billets')->nullOnDelete();
            $table->unsignedBigInteger('compagnie_id')->nullable()->index();
            $table->unsignedBigInteger('gare_id')->nullable()->index();
            $table->string('theme', 20);
            $table->string('objet', 120);
            $table->string('statut', 12)->default('nouvelle')->index();
            $table->string('rappel_statut', 10)->nullable(); // null, demande ou fait
            $table->foreignId('assigne_a')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('note')->nullable();
            $table->timestamp('derniere_activite_le')->nullable()->index();
            $table->timestamp('resolue_le')->nullable();
            $table->timestamps();
        });

        Schema::create('messages_assistance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('demande_id')->constrained('demandes_assistance')->cascadeOnDelete();
            $table->foreignId('auteur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('auteur_type', 6); // client ou staff
            $table->text('corps');
            $table->timestamp('lu_le')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages_assistance');
        Schema::dropIfExists('demandes_assistance');
    }
};
