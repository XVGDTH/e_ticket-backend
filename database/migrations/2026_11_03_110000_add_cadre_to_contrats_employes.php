<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contrats_employes', function (Blueprint $t) {
            $t->boolean('cadre')->default(false)->after('categorie');
        });
    }

    public function down(): void
    {
        Schema::table('contrats_employes', function (Blueprint $t) {
            $t->dropColumn('cadre');
        });
    }
};
