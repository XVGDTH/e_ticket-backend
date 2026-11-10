<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cpt_ecritures', function (Blueprint $t) {
            $t->string('origine', 16)->default('manuelle')->after('statut'); // manuelle, automatique, contrepassation
            $t->string('source_type', 60)->nullable()->after('origine');      // traçabilité jusqu'à la pièce (RG-CPT-01)
            $t->unsignedBigInteger('source_id')->nullable()->after('source_type');
            $t->boolean('est_facture')->default(false)->after('source_id');
            $t->string('tiers', 120)->nullable()->after('est_facture');
            $t->date('date_echeance')->nullable()->after('tiers');
            $t->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::table('cpt_ecritures', function (Blueprint $t) {
            $t->dropIndex(['source_type', 'source_id']);
            $t->dropColumn(['origine', 'source_type', 'source_id', 'est_facture', 'tiers', 'date_echeance']);
        });
    }
};
