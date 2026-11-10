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
        Schema::table('compagnies', function (Blueprint $table) {
            $table->unsignedSmallInteger('validite_jours')->default(12);
        });

        Schema::table('billets', function (Blueprint $table) {
            $table->unsignedSmallInteger('validite_jours')->nullable(); // durée appliquée à l'achat
            $table->timestamp('presence_le')->nullable();
            $table->boolean('presence_verifiee')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
