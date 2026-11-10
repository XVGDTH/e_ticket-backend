<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('tentatives_login')->default(0)->after('actif');
            $table->timestamp('verrouille_jusqua')->nullable()->after('tentatives_login');
            $table->timestamp('derniere_tentative_echec')->nullable()->after('verrouille_jusqua');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['tentatives_login', 'verrouille_jusqua', 'derniere_tentative_echec']);
        });
    }
};
