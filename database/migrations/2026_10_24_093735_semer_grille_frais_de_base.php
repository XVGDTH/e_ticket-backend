<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Ne crée la grille que si aucune règle générale n'existe déjà.
        $existe = DB::table('frais_regles')
            ->whereNull('compagnie_id')->whereNull('gare_id')->whereNull('route_transport_id')
            ->exists();

        if ($existe) {
            return;
        }

        $paliers = [
            ['national', 0, 1000, 100],
            ['national', 1001, 2500, 150],
            ['national', 2501, 3000, 200],
            ['national', 3001, 5999, 250],
            ['national', 6000, null, 300],
            ['international', 0, null, 1000],
        ];

        foreach ($paliers as [$trajet, $min, $max, $valeur]) {
            DB::table('frais_regles')->insert([
                'compagnie_id' => null,
                'gare_id' => null,
                'route_transport_id' => null,
                'trajet' => $trajet,
                'prix_min' => $min,
                'prix_max' => $max,
                'mode' => 'fixe',
                'valeur' => $valeur,
                'actif' => true,
                'note' => 'Grille de base',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('frais_regles')->where('note', 'Grille de base')->delete();
    }
};
