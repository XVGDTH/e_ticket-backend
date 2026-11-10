<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    // public function up(): void
    // {
    //     Schema::table('billets', function (Blueprint $table) {
    //         //
    //     });
    // }
    public function up(): void
    {
        Schema::table('billets', function (Blueprint $table) {
            $table->timestamp('date_paiement')->nullable()->change();
            $table->string('operateur_paiement')->nullable()->change();
            $table->index(['gare_id', 'date_voyage', 'statut']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('billets', function (Blueprint $table) {
            //
        });
    }
};
