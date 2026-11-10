<?php

namespace Tests\Unit;

use App\Models\Compagnie;
use App\Models\Gare;
use App\Models\RouteTransport;
use App\Services\Billetterie;
use Tests\TestCase;

class BilletteriePlacesTest extends TestCase
{
    public function test_places_guichet_respecte_le_quota_application_de_la_gare(): void
    {
        $compagnie = (new Compagnie)->forceFill(['places_par_depart' => 60]);
        $gare = (new Gare)->forceFill(['quota_app_pourcent' => 40]);
        $route = (new RouteTransport)
            ->setRelation('compagnie', $compagnie)
            ->setRelation('gareDepart', $gare);

        $this->assertSame(36, Billetterie::placesGuichet($route));
    }
}
