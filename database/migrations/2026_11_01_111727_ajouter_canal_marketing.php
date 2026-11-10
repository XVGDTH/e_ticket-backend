<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('marketing_app')->default(true);
        });

        Schema::table('marketing_consentements', function (Blueprint $table) {
            $table->string('canal', 10)->default('whatsapp');
        });

        Schema::table('marketing_campagnes', function (Blueprint $table) {
            $table->string('canal', 10)->default('whatsapp');
            $table->string('titre', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('marketing_campagnes', function (Blueprint $table) {
            $table->dropColumn(['canal', 'titre']);
        });

        Schema::table('marketing_consentements', function (Blueprint $table) {
            $table->dropColumn('canal');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('marketing_app');
        });
    }
};
