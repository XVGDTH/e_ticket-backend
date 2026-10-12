<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Contrôle préalable : on ne durcit pas des données déjà incohérentes.
        $routes = DB::table('route_transports as r')
            ->join('gares as g', 'g.id', '=', 'r.gare_depart_id')
            ->whereColumn('g.compagnie_id', '<>', 'r.compagnie_id')
            ->count();

        $billets = DB::table('billets as b')
            ->join('route_transports as r', 'r.id', '=', 'b.route_transport_id')
            ->whereColumn('b.gare_id', '<>', 'r.gare_depart_id')
            ->count();

        $avis = DB::table('avis_gares as a')
            ->join('billets as b', 'b.id', '=', 'a.billet_id')
            ->whereColumn('a.gare_id', '<>', 'b.gare_id')
            ->count();

        if ($routes + $billets + $avis > 0) {
            throw new \RuntimeException(
                "Données incohérentes : {$routes} route(s) départ d'une gare d'une autre compagnie, "
                . "{$billets} billet(s) dont la gare n'est pas celle de la route, "
                . "{$avis} avis sur une autre gare que celle du billet. Corrige-les avant de relancer."
            );
        }

        // Une route part d'une gare de SA compagnie.
        Schema::table('gares', function (Blueprint $table) {
            $table->unique(['id', 'compagnie_id'], 'gares_id_compagnie_unique');
        });

        Schema::table('route_transports', function (Blueprint $table) {
            $table->unique(['id', 'gare_depart_id'], 'routes_id_gare_depart_unique');
            $table->foreign(['gare_depart_id', 'compagnie_id'], 'routes_gare_compagnie_foreign')
                ->references(['id', 'compagnie_id'])->on('gares')->cascadeOnDelete();
        });

        // Un billet appartient à la gare de départ de sa route.
        Schema::table('billets', function (Blueprint $table) {
            $table->unique(['id', 'gare_id'], 'billets_id_gare_unique');
            $table->foreign(['route_transport_id', 'gare_id'], 'billets_route_gare_foreign')
                ->references(['id', 'gare_depart_id'])->on('route_transports');

            // Vitesse du tableau de bord.
            $table->index('date_paiement', 'billets_date_paiement_index');
            $table->index(['gare_id', 'date_voyage'], 'billets_gare_date_voyage_index');
        });

        // Un avis porte sur la gare de son billet.
        Schema::table('avis_gares', function (Blueprint $table) {
            $table->foreign(['billet_id', 'gare_id'], 'avis_billet_gare_foreign')
                ->references(['id', 'gare_id'])->on('billets')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('avis_gares', function (Blueprint $table) {
            $table->dropForeign('avis_billet_gare_foreign');
        });

        Schema::table('billets', function (Blueprint $table) {
            $table->dropForeign('billets_route_gare_foreign');
            $table->dropUnique('billets_id_gare_unique');
            $table->dropIndex('billets_date_paiement_index');
            $table->dropIndex('billets_gare_date_voyage_index');
        });

        Schema::table('route_transports', function (Blueprint $table) {
            $table->dropForeign('routes_gare_compagnie_foreign');
            $table->dropUnique('routes_id_gare_depart_unique');
        });

        Schema::table('gares', function (Blueprint $table) {
            $table->dropUnique('gares_id_compagnie_unique');
        });
    }
};