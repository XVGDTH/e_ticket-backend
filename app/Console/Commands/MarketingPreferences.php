<?php

namespace App\Console\Commands;

use App\Services\Marketing\Preferences;
use Illuminate\Console\Command;

class MarketingPreferences extends Command
{
    protected $signature = 'marketing:preferences';

    protected $description = 'Recalcule les préférences des clients par compagnie et par gare';

    public function handle(): int
    {
        $r = Preferences::recalculer();

        $this->info($r['lignes'].' ligne(s) calculée(s) : '.$r['segments']['fidele'].' fidèle(s), '
            .$r['segments']['regulier'].' régulier(s), '.$r['segments']['occasionnel'].' occasionnel(s), '
            .$r['segments']['inactif'].' inactif(s) (par compagnie).');

        return self::SUCCESS;
    }
}
