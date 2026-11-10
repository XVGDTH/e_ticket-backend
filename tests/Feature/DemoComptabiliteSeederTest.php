<?php

namespace Tests\Feature;

use App\Models\Compagnie;
use App\Models\CptCompte;
use App\Models\CptEcriture;
use App\Models\User;
use Database\Seeders\DemoComptabiliteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoComptabiliteSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_cree_des_ecritures_equilibrees_et_ne_les_duplique_pas(): void
    {
        $compagnie = Compagnie::factory()->create();
        $administrateur = User::create([
            'prenom' => 'Admin',
            'nom' => 'Compta',
            'telephone' => '70000003',
            'telephone_verifie' => true,
            'password' => 'motdepasse123',
        ]);
        $administrateur->assignerRolePourCompagnie('super_admin');

        foreach ([
            ['numero' => '401', 'intitule' => 'Fournisseurs', 'classe' => 4],
            ['numero' => '521', 'intitule' => 'Banque', 'classe' => 5],
            ['numero' => '571', 'intitule' => 'Caisse', 'classe' => 5],
            ['numero' => '6042', 'intitule' => 'Carburant', 'classe' => 6],
            ['numero' => '706', 'intitule' => 'Services vendus', 'classe' => 7],
        ] as $compte) {
            CptCompte::query()->create($compte + ['compagnie_id' => null, 'actif' => true]);
        }

        (new DemoComptabiliteSeeder)->run();
        (new DemoComptabiliteSeeder)->run();

        $ecritures = CptEcriture::query()->where('reference', 'like', 'DEMO-CPT-%')->get();

        $this->assertDatabaseCount('cpt_ecritures', 5);
        $this->assertDatabaseCount('cpt_lignes', 10);
        $this->assertSame(5, $ecritures->where('statut', 'comptabilise')->count());
        $this->assertSame((int) $compagnie->id, (int) $ecritures->first()->compagnie_id);

        foreach ($ecritures as $ecriture) {
            $this->assertSame(
                (int) $ecriture->lignes()->sum('debit'),
                (int) $ecriture->lignes()->sum('credit')
            );
        }
    }

    public function test_ne_cree_pas_d_ecritures_de_demo_en_production(): void
    {
        $this->app->detectEnvironment(static fn (): string => 'production');

        (new DemoComptabiliteSeeder)->run();

        $this->assertDatabaseCount('cpt_ecritures', 0);
        $this->assertDatabaseCount('cpt_lignes', 0);
    }
}
