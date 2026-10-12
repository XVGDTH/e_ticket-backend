<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SupprimerDonneesDemoSeeder extends Seeder
{
    public function run(): void
    {
        $billets = DB::table('billets')->where('reference', 'like', 'DEMO-%')->delete();

        $clients = DB::table('users')
            ->where('prenom', 'Demo')
            ->where('telephone', 'like', '990%')
            ->delete();

        $this->command?->info("Supprimés : {$billets} billets de démonstration et {$clients} clients fictifs.");
    }
}
