<?php

namespace Tests\Feature;

use App\Models\Compagnie;
use App\Models\Gare;
use App\Models\Horaire;
use App\Models\RouteTransport;
use App\Models\User;
use App\Models\Ville;
use App\Services\Analytics\VentesAnalytics;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsRemplissageTest extends TestCase
{
    use RefreshDatabase;

    public function test_departs_programmes_respectent_les_jours_de_la_semaine(): void
    {
        $compagnie = Compagnie::create(['nom' => 'CompagnieTest', 'slug' => 'compagnie-test', 'actif' => true]);
        $ville = Ville::create(['nom' => 'Ouagadougou', 'aliases' => []]);
        $gare = Gare::create(['compagnie_id' => $compagnie->id, 'ville_id' => $ville->id, 'quartier' => 'GareTest']);
        $route = RouteTransport::create([
            'compagnie_id' => $compagnie->id,
            'gare_depart_id' => $gare->id,
            'ville_arrivee_id' => $ville->id,
            'prix' => 5000,
            'actif' => true,
        ]);

        Horaire::create(['route_transport_id' => $route->id, 'heure' => '08:00:00']); // tous les jours
        Horaire::create(['route_transport_id' => $route->id, 'heure' => '14:00:00', 'jours_semaine' => ['Lun', 'mer']]);

        $admin = User::create([
            'prenom' => 'Super',
            'nom' => 'Admin',
            'telephone' => '73000001',
            'telephone_verifie' => true,
            'password' => 'motdepasse123',
        ]);
        $admin->assignerRolePourCompagnie('super_admin', null);

        $departs = VentesAnalytics::pour($admin)->departsProgrammes(
            Carbon::parse('2026-10-05'),
            Carbon::parse('2026-10-18')->endOfDay()
        );

        $this->assertSame(18, $departs);
    }
}