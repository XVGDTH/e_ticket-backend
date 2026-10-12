<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_compagnie_roles', function (Blueprint $table) {
            // Ville de l'administrateur de compagnie (null = toute la compagnie).
            $table->foreignId('ville_id')->nullable()->after('gare_id')
                ->constrained('villes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('user_compagnie_roles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ville_id');
        });
    }
};