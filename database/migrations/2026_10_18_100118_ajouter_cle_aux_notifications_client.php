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
        Schema::table('notifications_client', function (Blueprint $table) {
            $table->string('cle')->nullable()->after('billet_reference')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications_client', fn (Blueprint $table) => $table->dropColumn('cle'));
    }
};
