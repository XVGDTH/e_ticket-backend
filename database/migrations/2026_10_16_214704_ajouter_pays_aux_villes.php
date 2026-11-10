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
        Schema::table('villes', function (Blueprint $table) {
            $table->string('pays')->default('Burkina Faso')->after('nom');
            $table->boolean('etranger')->default(false)->after('pays');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Schema::table('villes', function (Blueprint $table) {
        //     $table->dropColumn(['pays', 'etranger']);
        // });
        Schema::table('villes', function (Blueprint $table) {
            $table->dropColumn(['pays', 'etranger']);
        });
    }
};
