<?php

use App\Support\PlanSyscohada;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::table('cpt_comptes')->whereNull('compagnie_id')->exists()) {
            return;
        }

        $lignes = [];

        foreach (PlanSyscohada::comptes() as $numero => $intitule) {
            $lignes[] = [
                'compagnie_id' => null,
                'numero' => (string) $numero,
                'intitule' => $intitule,
                'classe' => (int) substr((string) $numero, 0, 1),
                'actif' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach (array_chunk($lignes, 100) as $lot) {
            DB::table('cpt_comptes')->insert($lot);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('cpt_comptes')->whereNull('compagnie_id')->delete();
    }
};
