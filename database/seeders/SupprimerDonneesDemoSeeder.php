<?php

namespace Database\Seeders;

use App\Models\Employe;
use App\Models\PeriodePaie;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SupprimerDonneesDemoSeeder extends Seeder
{
    public function run(): void
    {
        $employes = Employe::query()->where('matricule', 'DEMO-RH-0001')->get();
        $periodesIds = DB::table('bulletins_paie')
            ->whereIn('employe_id', $employes->pluck('id'))
            ->pluck('periode_paie_id')
            ->unique();

        $employesSupprimes = $employes->each->delete()->count();

        PeriodePaie::query()
            ->whereIn('id', $periodesIds)
            ->whereDoesntHave('bulletins')
            ->delete();

        $billets = DB::table('billets')->where('reference', 'like', 'DEMO-%')->delete();

        $clients = DB::table('users')
            ->where('prenom', 'Demo')
            ->where('telephone', 'like', '990%')
            ->delete();

        $depensesMaintenance = DB::table('depenses_vehicules')
            ->where('reference', 'like', 'DEMO-MNT-%')
            ->delete();
        $vehiculesMaintenance = DB::table('vehicules')
            ->where('immatriculation', 'like', 'DEMO-MNT-%')
            ->delete();

        $this->command?->info("Supprimés : {$employesSupprimes} employé(s) fictif(s), {$billets} billets, {$clients} clients, {$depensesMaintenance} dépenses et {$vehiculesMaintenance} cars de démonstration.");
    }
}
