<?php

namespace App\Console\Commands;

use App\Services\Marketing\MarketingEnvoi;
use Illuminate\Console\Command;

class MarketingEnvoyer extends Command
{
    protected $signature = 'marketing:envoyer';

    protected $description = 'Envoie un lot de messages des campagnes WhatsApp en cours';

    public function handle(): int
    {
        $this->info(MarketingEnvoi::traiter().' message(s) envoyé(s).');

        return self::SUCCESS;
    }
}
