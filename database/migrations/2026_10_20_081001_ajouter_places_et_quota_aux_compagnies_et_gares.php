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
            $table->unsignedSmallInteger('places_par_depart')->default(60)->after('logo_url');
            $table->unsignedTinyInteger('quota_app_pourcent')->default(100)->after('places_par_depart');
        });

        Schema::table('gares', function (Blueprint $table) {
            $table->unsignedTinyInteger('quota_app_pourcent')->nullable(); // vide = quota de la compagnie
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
