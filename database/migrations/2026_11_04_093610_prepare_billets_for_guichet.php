<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Un client du guichet n'a pas de compte : le billet n'a pas d'utilisateur.
        Schema::table('billets', function (Blueprint $t) {
            $t->unsignedBigInteger('user_id')->nullable()->change();
        });

        Schema::table('billets', function (Blueprint $t) {
            $t->string('canal', 10)->default('app')->index();       // app | guichet
            $t->foreignId('vendu_par')->nullable()->constrained('users')->nullOnDelete();
            $t->unsignedBigInteger('caisse_id')->nullable()->index();
            $t->unsignedInteger('remise')->default(0);
            $t->string('nom_client', 120)->nullable();
            $t->string('telephone_client', 30)->nullable();
            $t->string('reference_paiement', 80)->nullable();
            $t->unsignedSmallInteger('impressions')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('billets', function (Blueprint $t) {
            $t->dropConstrainedForeignId('vendu_par');
            $t->dropColumn(['canal', 'caisse_id', 'remise', 'nom_client', 'telephone_client', 'reference_paiement', 'impressions']);
        });
    }
};
